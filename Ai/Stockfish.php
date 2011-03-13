<?php

namespace Bundle\LichessBundle\Ai;

use Bundle\LichessBundle\Notation\Forsyth;
use Bundle\LichessBundle\Document\Game;

class Stockfish implements AiInterface
{
    protected $options = array(
        'executable_path' => '/usr/bin/stockfish',
        'book_dir'       => '/usr/share/stockfish'
    );

    public function __construct(array $options = array())
    {
        $this->options = array_merge($this->options, $options);
    }

    public function move(Game $game, $level)
    {
        $forsyth     = new Forsyth();
        $fen = $forsyth->export($game);
        $move        = $this->getBestMove($fen, $level);

        return $move;
    }

    protected function getBestMove($fen, $level)
    {
        $command = $this->getPlayCommand($fen, $level);
        exec($command, $output, $code);
        if ($code !== 0 || !preg_match('/^bestmove\s\w{4,5}$/', $output[1])) {
            throw new \RuntimeException(sprintf('Can not run stockfish: '.$command.' '.implode("\n", $output)));
        }
        $move = preg_replace('/^bestmove\s(\w{2})(\w{2})\w?$/', '$1 $2', $output[1]);
        return $move;
    }

    protected function getPlayCommand($fen, $level)
    {
        return sprintf("%s<<EOF
debug on
position %s
go
quit
EOF",
            $this->options['executable_path'],
            $fen
        );
    }
}
