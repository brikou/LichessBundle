<?php

namespace Bundle\LichessBundle\Chess;

use Bundle\LichessBundle\Document\Game;
use Bundle\LichessBundle\Document\Player;
use Symfony\Component\DependencyInjection\ContainerAware;

class Generator extends ContainerAware
{
    /**
     * @return Game
     */
    public function createGame($variant = Game::VARIANT_STANDARD)
    {
        $game = new Game($variant);

        $game->addPlayer(new Player('white'));
        $game->addPlayer(new Player('black'));

        $this->getVariantGenerator($variant)->createPieces($game);

        $game->setCreator($game->getPlayer('white'));

        return $game;
    }

    public function getVariantGenerator($variant = Game::VARIANT_STANDARD)
    {
        if($variant === Game::VARIANT_960) {
            $generator = $this->container->get('lichess_generator_960');
        }
        else {
            $generator = $this->container->get('lichess_generator_standard');
        }

        return $generator;
    }

    /**
     * Regenerate game pieces for the given variant
     *
     * @return Game
     **/
    public function applyVariant(Game $game, $variant)
    {
        if($game->getVariant() === $variant) {
            return $game;
        }
        $game->setVariant($variant);

        $this->getVariantGenerator($variant)->createPieces($game);
    }

    /**
     * Creates a return game for the given player,
     * reverting players colors
     *
     * @param  Player the player who creates the return game
     * @return Player the new player on the new game
     **/
    public function createReturnGame(Player $player)
    {
        $game = $player->getGame();
        $variant = $game->getVariant();
        $nextGame = $this->createGame($variant);
        $nextPlayer = $nextGame->getPlayer($player->getOpponent()->getColor());
        $nextGame->setCreator($nextPlayer);
        $nextGame->getPlayer('white')->setUser($game->getPlayer('black')->getUser());
        $nextGame->getPlayer('black')->setUser($game->getPlayer('white')->getUser());
        $game->setNext($nextPlayer->getFullId());

        return $nextPlayer;
    }

    public function createGameForPlayer($color, $variant = Game::VARIANT_STANDARD)
    {
        if(!in_array($color, array('white', 'black'))) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid player color', $color));
        }
        $game = $this->createGame($variant);
        $game->setCreatorColor($color);
        return $game->getCreator();
    }

    /**
     * Create a game from a visual block notation like:
r bqkb r
 ppp ppp
p n  n
    p
B   P
     N
PPPP PPP
RNBQK  R
    */
    public function createGameFromVisualBlock($data)
    {
        $data = $this->fixVisualBlock($data);
        $game = new Game();

        $players = array();
        foreach(array('white', 'black') as $color) {
            $game->addPlayer(new Player($color));
        }
        $game->setCreator($game->getPlayer('white'));

        foreach(explode("\n", $data) as $_y => $line) {
            $y = 8-$_y;
            for($x=1; $x<9; $x++) {
                $byte = $line{$x-1};
                if(' ' === $byte) {
                    continue;
                }
                $color = ctype_lower($byte) ? 'black' : 'white';
                $player = $game->getPlayer($color);
                switch(strtolower($byte)) {
                    case 'p': $class = 'Pawn'; break;
                    case 'r': $class = 'Rook'; break;
                    case 'n': $class = 'Knight'; break;
                    case 'b': $class = 'Bishop'; break;
                    case 'q': $class = 'Queen'; break;
                    case 'k': $class = 'King'; break;
                }
                $fullClass = 'Bundle\\LichessBundle\\Document\\Piece\\'.$class;
                $player->addPiece(new $fullClass($x, $y));
            }
        }

        return $game;
    }

    static public function fixVisualBlock($data)
    {
        $lines = explode("\n", $data);
        foreach($lines as $y => $line) {
            // add missing spaces
            $lines[$y] .= str_repeat(' ', 8 - strlen($line));
        }

        return implode("\n", $lines);
    }
}
