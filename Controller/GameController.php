<?php

namespace Bundle\LichessBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Bundle\LichessBundle\Document\Game;
use Bundle\LichessBundle\Chess\Analyser;
use Bundle\LichessBundle\Chess\Manipulator;
use Bundle\LichessBundle\Document\Clock;
use Bundle\LichessBundle\Document\Stack;
use Bundle\LichessBundle\Persistence\QueueEntry;
use Bundle\LichessBundle\Form;
use ZendPaginatorAdapter\DoctrineMongoDBAdapter;
use Zend\Paginator\Paginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GameController extends Controller
{
    public function listCurrentAction()
    {
        $ids = $this->get('lichess.repository.game')->findRecentStartedGameIds(9);

        return $this->render('LichessBundle:Game:listCurrent.twig', array(
            'ids'         => $ids,
            'implodedIds' => implode(',', $ids),
            'nbGames'     => $this->get('lichess.repository.game')->getNbGames(),
            'nbMates'     => $this->get('lichess.repository.game')->getNbMates()
        ));
    }

    public function listCurrentInnerAction($ids)
    {
        return $this->render('LichessBundle:Game:listCurrentInner.twig', array(
            'games' => $this->get('lichess.repository.game')->findGamesByIds($ids)
        ));
    }

    public function listAllAction()
    {
        $query = $this->get('lichess.repository.game')->createRecentStartedOrFinishedQuery();

        return $this->render('LichessBundle:Game:listAll.twig', array(
            'games'    => $this->createPaginatorForQuery($query),
            'nbGames'  => $this->get('lichess.repository.game')->getNbGames(),
            'nbMates'  => $this->get('lichess.repository.game')->getNbMates(),
            'pagerUrl' => $this->generateUrl('lichess_list_all')
        ));
    }

    public function listCheckmateAction()
    {
        $query = $this->get('lichess.repository.game')->createRecentMateQuery();

        return $this->render('LichessBundle:Game:listMates.twig', array(
            'games'    => $this->createPaginatorForQuery($query),
            'nbGames'  => $this->get('lichess.repository.game')->getNbGames(),
            'nbMates'  => $this->get('lichess.repository.game')->getNbMates(),
            'pagerUrl' => $this->generateUrl('lichess_list_mates')
        ));
    }

    /**
     * Join a game and start it if new, or see it as a spectator
     */
    public function showAction($id)
    {
        $game = $this->findGame($id);

        if($this->get('request')->getMethod() === 'HEAD') {
            return $this->createResponse(sprintf('Game #%s', $id));
        }

        if($game->getIsStarted()) {
            return $this->forward('LichessBundle:Game:watch', array('id' => $id));
        }

        return $this->render('LichessBundle:Game:join.twig', array(
            'game'  => $game,
            'color' => $game->getCreator()->getOpponent()->getColor()
        ));
    }

    public function joinAction($id)
    {
        $game = $this->findGame($id);

        if($this->get('request')->getMethod() === 'HEAD') {
            return $this->createResponse(sprintf('Game #%s', $id));
        }

        if($game->getIsStarted()) {
            $this->get('lichess.logger')->warn($game, 'Game:join started');
            return $this->redirect($this->generateUrl('lichess_game', array('id' => $id)));
        }

        $this->get('lichess.blamer.player')->blame($game->getInvited());
        $game->start();
        $game->getCreator()->addEventToStack(array(
            'type' => 'redirect',
            'url'  => $this->generateUrl('lichess_player', array('id' => $game->getCreator()->getFullId()))
        ));
        $this->get('lichess.object_manager')->flush();
        $this->get('lichess.logger')->notice($game, 'Game:join');
        return $this->redirect($this->generateUrl('lichess_player', array('id' => $game->getInvited()->getFullId())));
    }

    public function watchAction($id)
    {
        $game = $this->findGame($id);
        $player = $game->getCreator();
        $analyser = new Analyser($game->getBoard());
        $isKingAttacked = $analyser->isKingAttacked($game->getTurnPlayer());
        if($isKingAttacked) {
            $checkSquareKey = $game->getTurnPlayer()->getKing()->getSquareKey();
        }
        else {
            $checkSquareKey = null;
        }
        $possibleMoves = ($player->isMyTurn() && $game->getIsPlayable()) ? 1 : null;

        return $this->render('LichessBundle:Player:watch.twig', array(
            'player'         => $player,
            'checkSquareKey' => $checkSquareKey,
            'possibleMoves'  => $possibleMoves
        ));
    }

    public function inviteFriendAction($color)
    {
        $isAuthenticated = $this->get('lichess.security.helper')->isAuthenticated();
        $config = new Form\FriendGameConfig();
        $config->fromArray($this->get('session')->get('lichess.game_config.friend', array()));
        if(!$isAuthenticated) {
            $config->mode = 0;
        }
        $formClass = 'Bundle\LichessBundle\Form\\'.($isAuthenticated ? 'FriendWithModeGameConfigForm' : 'FriendGameConfigForm');
        $form = new $formClass('config', $config, $this->get('validator'));
        if('POST' === $this->get('request')->getMethod()) {
            $form->bind($this->get('request')->request->get($form->getName()));
            if($form->isValid()) {
                $this->get('session')->set('lichess.game_config.friend', $config->toArray());
                $player = $this->get('lichess_generator')->createGameForPlayer($color, $config->variant);
                $this->get('lichess.blamer.player')->blame($player);
                $game = $player->getGame();
                if($config->time) {
                    $clock = new Clock($config->time * 60);
                    $game->setClock($clock);
                }
                $game->setIsRated($config->mode);
                $this->get('lichess.object_manager')->persist($game);
                $this->get('lichess.object_manager')->flush();
                $this->get('lichess.logger')->notice($game, 'Game:inviteFriend create');
                return $this->redirect($this->generateUrl('lichess_wait_friend', array('id' => $player->getFullId())));
            }
        }

        return $this->render('LichessBundle:Game:inviteFriend.twig', array(
            'form'  => $form,
            'color' => $color
        ));
    }

    public function inviteAiAction($color)
    {
        $config = new Form\AiGameConfig();
        $config->fromArray($this->get('session')->get('lichess.game_config.ai', array()));
        $form = new Form\AiGameConfigForm('config', $config, $this->get('validator'));
        if('POST' === $this->get('request')->getMethod()) {
            $form->bind($this->get('request')->request->get($form->getName()));
            if($form->isValid()) {
                $this->get('session')->set('lichess.game_config.ai', $config->toArray());
                $player = $this->get('lichess_generator')->createGameForPlayer($color, $config->variant);
                $this->get('lichess.blamer.player')->blame($player);
                $game = $player->getGame();
                $opponent = $player->getOpponent();
                $opponent->setIsAi(true);
                $opponent->setAiLevel(1);
                $game->start();

                if($player->isBlack()) {
                    $manipulator = new Manipulator($game, new Stack());
                    $manipulator->play($this->get('lichess_ai')->move($game, $opponent->getAiLevel()));
                }
                $this->get('lichess.object_manager')->persist($game);
                $this->get('lichess.object_manager')->flush();
                $this->get('lichess.logger')->notice($game, 'Game:inviteAi create');

                return $this->redirect($this->generateUrl('lichess_player', array('id' => $player->getFullId())));
            }
        }

        return $this->render('LichessBundle:Game:inviteAi.twig', array(
            'form'  => $form,
            'color' => $color
        ));
    }

    public function inviteAnybodyAction($color)
    {
        if($this->get('request')->getMethod() == 'HEAD') {
            return $this->createResponse('Lichess play chess with anybody');
        }
        $isAuthenticated = $this->get('lichess.security.helper')->isAuthenticated();
        $config = new Form\AnybodyGameConfig();
        $config->fromArray($this->get('session')->get('lichess.game_config.anybody', array()));
        if(!$isAuthenticated) {
            $config->modes = array(0);
        }
        $formClass = 'Bundle\LichessBundle\Form\\'.($isAuthenticated ? 'AnybodyWithModesGameConfigForm' : 'AnybodyGameConfigForm');
        $form = new $formClass('config', $config, $this->get('validator'));
        if('POST' === $this->get('request')->getMethod()) {
            $form->bind($this->get('request')->request->get($form->getName()));
            if($form->isValid()) {
                $this->get('session')->set('lichess.game_config.anybody', $config->toArray());
                $queue = $this->get('lichess.seek_queue');
                $result = $queue->add($config->variants, $config->times, $config->modes, $this->get('session')->get('lichess.session_id'), $color);
                $game = $result['game'];
                if(!$game) {
                    return $this->inviteAnybodyAction($color);
                }
                if($result['status'] === $queue::FOUND) {
                    if(!$this->get('lichess_synchronizer')->isConnected($game->getCreator())) {
                        $this->get('lichess.object_manager')->remove($game);
                        $this->get('lichess.object_manager')->flush();
                        $this->get('lichess.logger')->notice($game, 'Game:inviteAnybody remove');
                        return $this->inviteAnybodyAction($color);
                    }
                    $this->get('lichess.logger')->notice($game, 'Game:inviteAnybody join');
                    return $this->redirect($this->generateUrl('lichess_game', array('id' => $game->getId())));
                }
                $this->get('logger')->notice(sprintf('Game:inviteAnybody queue game:%s, mode:%s, variant:%s, time:%s', $game->getId(), implode(',', $config->getModeNames()), implode(',', $config->getVariantNames()), implode(',', $config->times)));
                return $this->redirect($this->generateUrl('lichess_wait_anybody', array('id' => $game->getCreator()->getFullId())));
            }
        }

        return $this->render('LichessBundle:Game:inviteAnybody.twig', array(
            'form'  => $form,
            'color' => $color
        ));
    }

    /**
     * Return the game for this id
     *
     * @param string $id
     * @return Game
     */
    protected function findGame($id)
    {
        $game = $this->get('lichess.repository.game')->findOneById($id);

        if(!$game) {
            throw new NotFoundHttpException('Can\'t find game '.$id);
        }

        return $game;
    }

    protected function createPaginatorForQuery($query)
    {
        $games = new Paginator(new DoctrineMongoDBAdapter($query));
        $games->setCurrentPageNumber($this->get('request')->query->get('page', 1));
        $games->setItemCountPerPage(10);
        $games->setPageRange(10);

        return $games;
    }
}
