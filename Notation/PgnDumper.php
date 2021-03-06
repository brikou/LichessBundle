<?php

namespace Bundle\LichessBundle\Notation;
use Bundle\LichessBundle\Document\Game;
use Bundle\LichessBundle\Document\Player;
use Bundle\LichessBundle\Document\Piece;
use Bundle\LichessBundle\Chess\Square;
use Symfony\Component\Routing\Router;

/**
 * http://www.chessclub.com/help/PGN-spec
 */
class PgnDumper
{
    protected $urlGenerator;

    /**
     * Constructor.
     *
     * @param Router $router A Router instance
     */
    public function __construct(Router $router = null)
    {
        if($router) {
            $this->urlGenerator = $router->getGenerator();
        }
    }

    protected function isCastleKingSide(Piece $king, Square $to)
    {
        if($to->getPiece()) {
            return $to->getX() > $king->getX();
        }
        else {
            return 7 === $to->getX();
        }
    }

    /**
     * Dumps a single move to PGN notation
     *
     * @return string
     **/
    public function dumpMove(Game $game, Piece $piece, Square $from, Square $to, array $playerPossibleMoves, $killed, $isCastling, $isPromotion, $isEnPassant, array $options)
    {
        $board = $game->getBoard();
        $pieceClass = $piece->getClass();
        $fromKey = $from->getKey();
        $toKey = $to->getKey();

        if($isCastling) {
            if($this->isCastleKingSide($piece, $to)) {
                return 'O-O';
            } else {
                return 'O-O-O';
            }
        }
        if($isEnPassant) {
            return $from->getFile().'x'.$to->getKey();
        }
        if($isPromotion) {
            return $to->getKey().'='.('Knight' === $options['promotion'] ? 'N' : $options['promotion']{0});
        }
        $pgnFromPiece = $pgnFromFile = $pgnFromRank = '';
        if('Pawn' != $pieceClass) {
            $pgnFromPiece = $piece->getPgn();
            $candidates = array();
            foreach($playerPossibleMoves as $_from => $_tos) {
                if($_from !== $fromKey && in_array($toKey, $_tos)) {
                    $_piece = $board->getPieceByKey($_from);
                    if($_piece->getClass() === $pieceClass) {
                        $candidates[] = $_piece;
                    }
                }
            }
            if(!empty($candidates)) {
                $isAmbiguous = false;
                foreach($candidates as $candidate) {
                    if($candidate->getSquare()->getFile() === $from->getFile()) {
                        $isAmbiguous = true;
                        break;
                    }
                }
                if(!$isAmbiguous) {
                    $pgnFromFile = $from->getFile();
                }
                else {
                    $isAmbiguous = false;
                    foreach($candidates as $candidate) {
                        if($candidate->getSquare()->getRank() === $from->getRank()) {
                            $isAmbiguous = true;
                            break;
                        }
                    }
                    if(!$isAmbiguous) {
                        $pgnFromRank = $from->getRank();
                    }
                    else {
                        $pgnFromFile = $from->getFile();
                        $pgnFromRank = $from->getRank();
                    }
                }
            }
        }
        if($killed) {
            $pgnCapture = 'x';
            if('Pawn' === $pieceClass) {
                $pgnFromFile = $from->getFile();
            }
        }
        else {
            $pgnCapture = '';
        }
        $pgnTo = $to->getKey();

        $pgn = $pgnFromPiece.$pgnFromFile.$pgnFromRank.$pgnCapture.$pgnTo;

        return $pgn;
    }

    /**
     * Produces PGN notation for a game
     *
     * @return string
     **/
    public function dumpGame(Game $game)
    {
        $result = $this->getPgnResult($game);
        $header = $this->getPgnHeader($game);
        $moves = $this->getPgnMoves($game);

        $pgn = $header."\n\n".$moves;

        if(!empty($moves)) {
            $pgn .= ' ';
        }

        $pgn .= $result;

        return $pgn;
    }

    public function getPgnMoves(Game $game)
    {
        $pgnMoves = $game->getPgnMoves();
        if(empty($pgnMoves)) {
            return '';
        }
        $moves = $game->getPgnMoves();
        $nbMoves = count($moves);
        $nbTurns = ceil($nbMoves/2);
        $string = '';
        for($turns = 1; $turns <= $nbTurns; $turns++) {
            $string .= $turns.'.';
            $string .= $moves[($turns-1)*2].' ';
            if(isset($moves[($turns-1)*2+1])) {
                $string .= $moves[($turns-1)*2+1].' ';
            }
        }

        return trim($string);
    }

    protected function getPgnHeader(Game $game)
    {
        return sprintf('[Event "%s"]%s[Site "%s"]%s[Date "%s"]%s[White "%s"]%s[Black "%s"]%s[Result "%s"]%s[Variant "%s"]%s[FEN "%s"]',
            $this->getEventName($game), "\n",
            $this->getGameUrl($game), "\n",
            $this->getGameDate($game), "\n",
            $this->getPgnPlayer($game->getPlayer('white')), "\n",
            $this->getPgnPlayer($game->getPlayer('black')), "\n",
            $this->getPgnResult($game), "\n",
            ucfirst($game->getVariantName()), "\n",
            $game->getInitialFen()
        );
    }

    protected function getEventName($game)
    {
        return $game->getIsRated() ? 'Rated Game' : 'Casual game';
    }

    protected function getGameDate($game)
    {
        return $game->getCreatedAt() ? $game->getCreatedAt()->format('Y.m.d') : '?';
    }

    protected function getGameUrl(Game $game)
    {
        if(null === $this->urlGenerator) {
            return 'http://lichess.org/';
        }

        return $this->urlGenerator->generate('lichess_pgn_viewer', array('id' => $game->getId()), true);
    }

    protected function getPgnResult(Game $game)
    {
        if($game->getIsFinished()) {
            if($game->getPlayer('white')->getIsWinner()) {
                return '1-0';
            }
            elseif($game->getPlayer('black')->getIsWinner()) {
                return '0-1';
            }
            return '1/2-1/2';
        }
        return '*';
    }

    protected function getPgnPlayer(Player $player)
    {
        if($player->getIsAi()) {
            return 'Crafty level '.$player->getAiLevel();
        }

        return $player->getUsernameWithElo();
    }
}
