<?php

namespace Bundle\LichessBundle\Ai;

use Bundle\LichessBundle\Chess\Analyser;
use Bundle\LichessBundle\Document\Game;

/**
 * This stupid AI plays a valid move randomly chosen
 */
class Stupid implements AiInterface
{
    public function move(Game $game, $level)
    {
        $analyser = new Analyser($game->getBoard());
        $moveTree = $analyser->getPlayerPossibleMoves($game->getTurnPlayer());

        // choose random piece
        do {
            $from = array_rand($moveTree);
        }
        while(empty($moveTree[$from]));

        // choose random move
        $to = $moveTree[$from][array_rand($moveTree[$from])];

        return $from.' '.$to;
    }
}
