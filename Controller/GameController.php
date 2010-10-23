<?php

namespace Bundle\LichessBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Bundle\LichessBundle\Entities\Game;
use Bundle\LichessBundle\Chess\Analyser;
use Bundle\LichessBundle\Chess\Manipulator;
use Bundle\LichessBundle\Chess\Clock;
use Bundle\LichessBundle\Stack;
use Bundle\LichessBundle\Form;
use Bundle\LichessBundle\Persistence\QueueEntry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GameController extends Controller
{
    public function listAction()
    {
        $hashes = $this['lichess_persistence']->findRecentGamesHashes(9);
        $hashes = implode(',', $hashes);

        return $this->render('LichessBundle:Game:list.php', array('hashes' => $hashes));
    }

    public function listInnerAction($hashes)
    {
        $hashes = explode(',', $hashes);
        $games = $this['lichess_persistence']->findGamesByHashes($hashes);

        return $this->render('LichessBundle:Game:listInner.php', array('games' => $games));
    }

    /**
     * Join a game and start it if new, or see it as a spectator
     */
    public function showAction($hash)
    {
        $game = $this->findGame($hash);

        if($game->getIsStarted()) {
            return $this->forward('LichessBundle:Game:watch', array('hash' => $hash));
        }

        $game->start();
        $game->getCreator()->getStack()->addEvent(array(
            'type' => 'redirect',
            'url' => $this->generateUrl('lichess_player', array('hash' => $game->getCreator()->getFullHash()))
        ));
        $this['lichess_persistence']->save($game);
        return $this->redirect($this->generateUrl('lichess_player', array('hash' => $game->getInvited()->getFullHash())));
    }

    public function watchAction($hash)
    {
        $game = $this->findGame($hash);
        $color = 'white';
        $player = $game->getPlayer($color);
        $analyser = new Analyser($game->getBoard());
        $isKingAttacked = $analyser->isKingAttacked($game->getTurnPlayer());
        if($isKingAttacked) {
            $checkSquareKey = $game->getTurnPlayer()->getKing()->getSquareKey();
        }
        else {
            $checkSquareKey = null;
        }
        $possibleMoves = ($player->isMyTurn() && !$game->getIsFinished()) ? 1 : null;

        return $this->render('LichessBundle:Game:watch.php', array('game' => $game, 'player' => $player, 'checkSquareKey' => $checkSquareKey, 'parameters' => $this->container->getParameterBag()->all(), 'possibleMoves' => $possibleMoves));
    }

    public function inviteFriendAction($color)
    {
        $config = new Form\FriendGameConfig($this['lichess_translator']);
        if($this['session']->has('lichess.game_config.time')) {
            $config->time = $this['session']->get('lichess.game_config.time');
        }
        $form = new Form\FriendGameConfigForm('config', $config, $this['validator']);
        if('POST' === $this['request']->getMethod()) {
            $form->bind($this['request']->request->get($form->getName()));
            if($form->isValid()) {
                $this['session']->set('lichess.game_config.time', $config->time);
                $player = $this['lichess_generator']->createGameForPlayer($color);
                if($config->time) {
                    $clock = new Clock($config->time * 60);
                    $player->getGame()->setClock($clock);
                }
                $this['lichess_persistence']->save($player->getGame());
                return $this->redirect($this->generateUrl('lichess_wait_friend', array('hash' => $player->getFullHash())));
            }
        }

        return $this->render('LichessBundle:Game:inviteFriend.php', array('form' => $this['templating.form']->get($form), 'color' => $color));
    }

    public function inviteAiAction($color)
    {
        $player = $this['lichess_generator']->createGameForPlayer($color);
        $game = $player->getGame();
        $opponent = $player->getOpponent();
        $opponent->setIsAi(true);
        $opponent->setAiLevel(1);
        $game->start();

        if($player->isBlack()) {
            $manipulator = new Manipulator($game, new Stack());
            $manipulator->play($this->container->getLichessAiService()->move($game, $opponent->getAiLevel()));
        }
        $this['lichess_persistence']->save($game);

        return $this->redirect($this->generateUrl('lichess_player', array('hash' => $player->getFullHash())));
    }

    public function inviteAnybodyAction($color)
    {
        $config = new Form\AnybodyGameConfig($this['lichess_translator']);
        if($this['session']->has('lichess.game_config.times')) {
            $config->times = $this['session']->get('lichess.game_config.times');
        }
        $form = new Form\AnybodyGameConfigForm('config', $config, $this['validator']);
        if('POST' === $this['request']->getMethod()) {
            $form->bind($this['request']->request->get($form->getName()));
            if($form->isValid()) {
                $this['session']->set('lichess.game_config.times', $config->times);
                $queueEntry = new QueueEntry($config->times, $this['session']->get('lichess.user_id'));
                $queue = $this['lichess_queue'];
                $result = $queue->add($queueEntry, $color);
                if($result['status'] === $queue::FOUND) {
                    $game = $this->findGame($result['game_hash']);
                    if($result['time']) {
                        $clock = new Clock($result['time'] * 60);
                        $game->setClock($clock);
                        $this['lichess_persistence']->save($game);
                    }
                    if($this['lichess_synchronizer']->isConnected($game->getCreator())) {
                        return $this->redirect($this->generateUrl('lichess_game', array('hash' => $game->getHash())));
                    }
                    $this['lichess_persistence']->remove($game);
                    return $this->inviteAnybodyAction($color);
                }
                $game = $result['game'];
                $this['lichess_persistence']->save($game);
                return $this->redirect($this->generateUrl('lichess_wait_anybody', array('hash' => $game->getCreator()->getFullHash())));
            }
        }

        return $this->render('LichessBundle:Game:inviteAnybody.php', array('form' => $this['templating.form']->get($form), 'color' => $color));
    }

    public function inviteAnybodyActionOld($color)
    {
        $connectionFile = $this->container->getParameter('lichess.anybody.connection_file');
        $persistence = $this['lichess_persistence'];
        $sessionInvites = $this['session']->get('lichess.invites', array());
        if(file_exists($connectionFile)) {
            $opponentHash = file_get_contents($connectionFile);
            // if I'm about to join my own game
            if(in_array($opponentHash, $sessionInvites)) {
                return $this->redirect($this->generateUrl('lichess_wait_anybody', array('hash' => $opponentHash)));
            }
            unlink($connectionFile);
            $gameHash = substr($opponentHash, 0, 6);
            $game = $persistence->find($gameHash);
            if($game) {
                if($this['lichess_synchronizer']->isConnected($game->getCreator())) {
                    return $this->redirect($this->generateUrl('lichess_game', array('hash' => $game->getHash())));
                }
                // if the game creator is disconnected, remove the game
                $persistence->remove($game);
            }
        }

        $player = $this['lichess_generator']->createGameForPlayer($color);
        $persistence->save($player->getGame());
        file_put_contents($connectionFile, $player->getFullHash());
        $sessionInvites[] = $player->getFullHash();
        $this['session']->set('lichess.invites', $sessionInvites);
        return $this->redirect($this->generateUrl('lichess_wait_anybody', array('hash' => $player->getFullHash())));
    }

    /**
     * Return the game for this hash
     *
     * @param string $hash
     * @return Game
     */
    protected function findGame($hash)
    {
        $game = $this['lichess_persistence']->find($hash);

        if(!$game) {
            throw new NotFoundHttpException('Can\'t find game '.$hash);
        }

        return $game;
    }
}
