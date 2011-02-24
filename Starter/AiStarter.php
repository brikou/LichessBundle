<?php

namespace Bundle\LichessBundle\Starter;

use Bundle\LichessBundle\Ai\AiInterface;
use Bundle\LichessBundle\Blamer\PlayerBlamer;
use Bundle\LichessBundle\Document\Game;
use Bundle\LichessBundle\Document\Player;
use Bundle\LichessBundle\Document\Stack;
use Bundle\LichessBundle\Logger;
use Bundle\LichessBundle\Form\GameConfig;
use Bundle\LichessBundle\Chess\Generator;
use Bundle\LichessBundle\Chess\Manipulator;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Session;

class AiStarter implements StarterInterface
{
    protected $generator;
    protected $playerBlamer;
    protected $ai;
    protected $objectManager;
    protected $logger;
    protected $session;

    public function __construct(Generator $generator, PlayerBlamer $playerBlamer, AiInterface $ai, DocumentManager $objectManager, Logger $logger, Session $session = null)
    {
        $this->generator = $generator;
        $this->playerBlamer = $playerBlamer;
        $this->ai = $ai;
        $this->objectManager = $objectManager;
        $this->logger = $logger;
        $this->session = $session;
    }

    public function start(GameConfig $config, $color)
    {
        if($this->session) {
            $this->session->set('lichess.game_config.ai', $config->toArray());
        }
        $player = $this->generator->createGameForPlayer($color, $config->variant);
        $this->playerBlamer->blame($player);
        $game = $player->getGame();
        $opponent = $player->getOpponent();
        $opponent->setIsAi(true);
        $opponent->setAiLevel(1);
        $game->start();

        if($player->isBlack()) {
            $manipulator = new Manipulator($game, new Stack());
            $manipulator->play($this->ai->move($game, $opponent->getAiLevel()));
        }
        $this->objectManager->persist($game);
        $this->objectManager->flush(array('safe' => true));
        $this->logger->notice($game, 'Game:inviteAi create');

        return $player;
    }
}