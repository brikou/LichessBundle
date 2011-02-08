<?php

namespace Bundle\LichessBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Bundle\LichessBundle\Document\Game;
use Bundle\LichessBundle\Chess\Analyser;
use Bundle\LichessBundle\Chess\Manipulator;
use Bundle\LichessBundle\Document\Clock;
use Bundle\LichessBundle\Document\Stack;
use ZendPaginatorAdapter\DoctrineMongoDBAdapter;
use Zend\Paginator\Paginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GameController extends Controller
{
    public function listCurrentAction()
    {
        $ids = $this->get('lichess.repository.game')->findRecentStartedGameIds(9);

        return $this->render('LichessBundle:Game:listCurrent.html.twig', array(
            'ids'         => $ids,
            'implodedIds' => implode(',', $ids),
            'nbGames'     => $this->get('lichess.repository.game')->getNbGames(),
            'nbMates'     => $this->get('lichess.repository.game')->getNbMates()
        ));
    }

    public function listCurrentInnerAction($ids)
    {
        return $this->render('LichessBundle:Game:listCurrentInner.html.twig', array(
            'games' => $this->get('lichess.repository.game')->findGamesByIds($ids)
        ));
    }

    public function listAllAction()
    {
        $query = $this->get('lichess.repository.game')->createRecentStartedOrFinishedQuery();

        return $this->render('LichessBundle:Game:listAll.html.twig', array(
            'games'    => $this->createPaginatorForQuery($query),
            'nbGames'  => $this->get('lichess.repository.game')->getNbGames(),
            'nbMates'  => $this->get('lichess.repository.game')->getNbMates(),
            'pagerUrl' => $this->generateUrl('lichess_list_all')
        ));
    }

    public function listCheckmateAction()
    {
        $query = $this->get('lichess.repository.game')->createRecentMateQuery();

        return $this->render('LichessBundle:Game:listMates.html.twig', array(
            'games'    => $this->createPaginatorForQuery($query),
            'nbGames'  => $this->get('lichess.repository.game')->getNbGames(),
            'nbMates'  => $this->get('lichess.repository.game')->getNbMates(),
            'pagerUrl' => $this->generateUrl('lichess_list_mates')
        ));
    }

    /**
     * Join a game and start it if new, or see it as a spectator
     */
    public function showAction($id, $color)
    {
        $game = $this->findGame($id);

        if($this->get('request')->getMethod() === 'HEAD') {
            return $this->createResponse(sprintf('Game #%s', $id));
        }

        if($game->getIsStarted()) {
            return $this->forward('LichessBundle:Game:watch', array('id' => $id, 'color' => $color));
        }

        return $this->render('LichessBundle:Game:join.html.twig', array(
            'game'  => $game,
            'color' => $game->getInvited()->getColor()
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
        $this->get('lichess.object_manager')->flush(array('safe' => true));
        $this->get('lichess.logger')->notice($game, 'Game:join');
        return $this->redirect($this->generateUrl('lichess_player', array('id' => $game->getInvited()->getFullId())));
    }

    public function watchAction($id, $color)
    {
        $game = $this->findGame($id);
        $player = $game->getPlayer($color);
        if($player->getIsAi()) {
            return $this->redirect($this->generateUrl('lichess_game', array('id' => $id, 'color' => $player->getOpponent()->getColor())));
        }
        $analyser = new Analyser($game->getBoard());
        $isKingAttacked = $analyser->isKingAttacked($game->getTurnPlayer());
        if($isKingAttacked) {
            $checkSquareKey = $game->getTurnPlayer()->getKing()->getSquareKey();
        }
        else {
            $checkSquareKey = null;
        }
        $possibleMoves = ($player->isMyTurn() && $game->getIsPlayable()) ? 1 : null;

        return $this->render('LichessBundle:Player:watch.html.twig', array(
            'player'         => $player,
            'game'           => $game,
            'checkSquareKey' => $checkSquareKey,
            'possibleMoves'  => $possibleMoves
        ));
    }

    public function inviteFriendAction($color)
    {
        $form = $this->get('lichess.form.manager')->createFriendForm();
        if('POST' === $this->get('request')->getMethod()) {
            $form->bind($this->get('request')->request->get($form->getName()));
            if($form->isValid()) {
                $config = $form->getData();
                $this->get('session')->set('lichess.game_config.friend', $config->toArray());
                $player = $this->get('lichess_generator')->createGameForPlayer($color, $config->variant);
                $this->get('lichess.blamer.player')->blame($player);
                $game = $player->getGame();
                if($config->time) {
                    $clock = new Clock($config->time * 60, $config->increment);
                    $game->setClock($clock);
                }
                $game->setIsRated($config->mode);
                $this->get('lichess.object_manager')->persist($game);
                $this->get('lichess.object_manager')->flush(array('safe' => true));
                $this->get('lichess.logger')->notice($game, 'Game:inviteFriend create');
                return $this->redirect($this->generateUrl('lichess_wait_friend', array('id' => $player->getFullId())));
            }
        }

        return $this->render('LichessBundle:Game:inviteFriend.html.twig', array(
            'form'  => $form,
            'color' => $color
        ));
    }

    public function inviteAiAction($color)
    {
        $form = $this->get('lichess.form.manager')->createAiForm();
        $form->bind($this->get('request'), $form->getData());

        if($form->isValid()) {
            $player = $this->get('lichess.starter')->startAi($form->getData(), $color);

            return $this->redirect($this->generateUrl('lichess_player', array('id' => $player->getFullId())));
        }

        return $this->render('LichessBundle:Game:inviteAi.html.twig', array(
            'form'  => $form,
            'color' => $color
        ));
    }

    public function inviteAnybodyAction($color)
    {
        $form = $this->get('lichess.form.manager')->createAnybodyForm();
        if('POST' === $this->get('request')->getMethod()) {
            $form->bind($this->get('request')->request->get($form->getName()));
            if($form->isValid()) {
                $config = $form->getData();
                $this->get('session')->set('lichess.game_config.anybody', $config->toArray());
                $queue = $this->get('lichess.seek_queue');
                $result = $queue->add($config->variants, $config->times, $config->increments, $config->modes, $this->get('session')->get('lichess.session_id'), $color);
                $game = $result['game'];
                if(!$game) {
                    return $this->inviteAnybodyAction($color);
                }
                if($result['status'] === $queue::FOUND) {
                    if(!$this->get('lichess_synchronizer')->isConnected($game->getCreator())) {
                        $this->get('lichess.object_manager')->remove($game);
                        $this->get('lichess.object_manager')->flush(array('safe' => true));
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

        return $this->render('LichessBundle:Game:inviteAnybody.html.twig', array(
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
