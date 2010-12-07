<?php

namespace Bundle\LichessBundle\Tests\Chess;

use Bundle\LichessBundle\Tests\ChessTest;
use Bundle\LichessBundle\Chess\Generator;
use Bundle\LichessBundle\Chess\Generator\Chess960PositionGenerator;
use Bundle\LichessBundle\Model;

class GeneratorTest extends ChessTest
{
    public function testGameCreationStandard()
    {
        $generator = $this->getGenerator();

        $game = $generator->createGame();

        $this->assertTrue($game instanceof Model\Game);
        $this->assertEquals(0, $game->getTurns());
        $this->assertEquals(false, $game->getIsStarted());
        $this->assertEquals(false, $game->getIsFinished());

        return $game;
    }

    public function testGameCreationStandard960()
    {
        $generator = $this->getGenerator();

        $game = $generator->createGame(Model\Game::VARIANT_960);

        $this->assertTrue($game instanceof Model\Game);
        $this->assertEquals(0, $game->getTurns());
        $this->assertEquals(false, $game->getIsStarted());
        $this->assertEquals(false, $game->getIsFinished());

        return $game;
    }

    public function testFromVisual()
    {
        $visual = <<<EOF
r bqkb r
 ppp ppp
p n  n
    p
B   P
     N
PPPP PPP
RNBQK  R
EOF;
        $generator = $this->getGenerator();
        $game = $generator->createGameFromVisualBlock($visual);
        $this->assertEquals("\n".$generator->fixVisualBlock($visual)."\n", $game->getBoard()->dump());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testGamePlayers(Model\Game $game)
    {
        $this->assertEquals(2, count($game->getPlayers()));

        $player = $game->getPlayer('white');
        $this->assertTrue($player instanceof Model\Player);
        $this->assertEquals('white', $player->getColor());
        $this->assertEquals(false, $player->getIsWinner());
        $this->assertEquals(false, $player->getIsAi());
        $this->assertSame($game, $player->getGame());
        $this->assertSame($game->getPlayer('black'), $player->getOpponent());
        $this->assertSame($player, $player->getOpponent()->getOpponent());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testGamePlayerTurn(Model\Game $game)
    {
        $player = $game->getPlayer('white');
        $this->assertTrue($player->getIsMyTurn());
        $this->assertFalse($player->getOpponent()->getIsMyTurn());

        $game->setTurns($game->getTurns()+1);
        $this->assertFalse($player->getIsMyTurn());
        $this->assertTrue($player->getOpponent()->getIsMyTurn());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testGameGetWinner(Model\Game $game)
    {
        $this->assertNull($game->getWinner());

        $game->setWinner($game->getPlayer('white'));
        $this->assertSame($game->getPlayer('white'), $game->getWinner());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testGamePieces(Model\Game $game)
    {
        $this->assertEquals(32, count($game->getPieces()));
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testPlayerPieces(Model\Game $game)
    {
        $player = $game->getPlayer('white');
        $this->assertEquals(16, count($player->getPieces()));
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testPlayerKing(Model\Game $game)
    {
        $player = $game->getPlayer('white');
        $king = $player->getKing();
        $this->assertTrue($king instanceof Model\Piece\King);
        $this->assertEquals(1, $king->getY());
        $this->assertSame($player, $king->getPlayer());
        $this->assertTrue($king->isClass('King'));
        $this->assertFalse($king->getIsDead());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testPlayerPawns(Model\Game $game)
    {
        $player = $game->getPlayer('white');
        $pawns = $player->getPiecesByClass('Pawn');
        $this->assertTrue($pawns[0] instanceof Model\Piece\Pawn);
        $this->assertEquals(2, $pawns[0]->getY());
        $this->assertEquals(8, count($pawns));
        $this->assertSame($player, $pawns[0]->getPlayer());
        $this->assertTrue($pawns[0]->isClass('Pawn'));
        $this->assertFalse($pawns[0]->getIsDead());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testPlayerRooks(Model\Game $game)
    {
        $player = $game->getPlayer('white');
        $rooks = $player->getPiecesByClass('Rook');
        $this->assertTrue($rooks[0] instanceof Model\Piece\Rook);
        $this->assertEquals(1, $rooks[0]->getY());
        $this->assertEquals(2, count($rooks));
        $this->assertSame($player, $rooks[0]->getPlayer());
        $this->assertTrue($rooks[0]->isClass('Rook'));
        $this->assertFalse($rooks[0]->getIsDead());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testPlayerKnights(Model\Game $game)
    {
        $player = $game->getPlayer('white');
        $knights = $player->getPiecesByClass('Knight');
        $this->assertTrue($knights[0] instanceof Model\Piece\Knight);
        $this->assertEquals(1, $knights[0]->getY());
        $this->assertEquals(2, count($knights));
        $this->assertSame($player, $knights[0]->getPlayer());
        $this->assertTrue($knights[0]->isClass('Knight'));
        $this->assertFalse($knights[0]->getIsDead());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testPlayerBishops(Model\Game $game)
    {
        $player = $game->getPlayer('white');
        $bishops = $player->getPiecesByClass('Bishop');
        $this->assertTrue($bishops[0] instanceof Model\Piece\Bishop);
        $this->assertEquals(1, $bishops[0]->getY());
        $this->assertEquals(2, count($bishops));
        $this->assertSame($player, $bishops[0]->getPlayer());
        $this->assertTrue($bishops[0]->isClass('Bishop'));
        $this->assertFalse($bishops[0]->getIsDead());
    }

    /**
     * @depends testGameCreationStandard
     */
    public function testPlayerQueens(Model\Game $game)
    {
        $player = $game->getPlayer('white');
        $queens = $player->getPiecesByClass('Queen');
        $this->assertTrue($queens[0] instanceof Model\Piece\Queen);
        $this->assertEquals(1, $queens[0]->getY());
        $this->assertEquals(1, count($queens));
        $this->assertSame($player, $queens[0]->getPlayer());
        $this->assertTrue($queens[0]->isClass('Queen'));
        $this->assertFalse($queens[0]->getIsDead());
    }
}
