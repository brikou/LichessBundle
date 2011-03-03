<?php

namespace Bundle\LichessBundle\Ai;
use Bundle\LichessBundle\Notation\Forsyth;
use Bundle\LichessBundle\Document\Game;

class Crafty implements AiInterface
{
    protected $options = array(
        'executable_path' => '/usr/bin/crafty'
    );

    public function __construct(array $options = array())
    {
        $this->options = array_merge($this->options, $options);
    }

    public function move(Game $game, $level)
    {
        $forsyth    = new Forsyth();
        $oldForsyth = $forsyth->export($game);
        $oldForsyth = $this->removeCastlingInfos($oldForsyth);
        $newForsyth = $this->getNewForsyth($oldForsyth, $level);
        $move       = $forsyth->diffToMove($game, $newForsyth);

        return $move;
    }

    protected function removeCastlingInfos($forsyth)
    {
        return preg_replace('#^([\w\d/]+)\s(w|b)\s(?:[kq\-]+)\s(.+)$#i', '$1 $2 - $3', $forsyth);
    }

    protected function getNewForsyth($forsythNotation, $level)
    {
        $file = tempnam(sys_get_temp_dir(), 'lichess_crafty_'.md5(uniqid().mt_rand(0, 1000)));
        touch($file);

        $command = $this->getPlayCommand($forsythNotation, $file, $level);
        exec($command, $output, $code);
        if($code !== 0)
        {
            throw new \RuntimeException(sprintf('Can not run crafty: '.$command.' '.implode("\n", $output)));
        }

        $forsyth = $this->extractForsyth(file($file, FILE_IGNORE_NEW_LINES));
        unlink($file);

        if(!$forsyth)
        {
            throw new \RuntimeException(sprintf('Can not run crafty: '.$command.' '.implode("\n", $output)));
        }

        return $forsyth;
    }

    protected function extractForsyth($results)
    {
        return str_replace('setboard ', '', $results[0]);
    }

    protected function getPlayCommand($forsythNotation, $file, $level)
    {
        return sprintf("cd %s && %s log=off ponder=off smpmt=1 %s <<EOF
setboard %s
move
savepos %s
quit
EOF",
            dirname($file),
            $this->options['executable_path'],
            $this->getCraftyLevel($level),
            $forsythNotation,
            basename($file)
        );
    }

    protected function getCraftyLevel($level)
    {
        $config = array(
            /*
            * sd is the number of moves crafty can anticipate
            */
            'sd='.$level,
            /*
            * st is the time in seconds crafty can think about the situation
            */
            'st='.$this->getTimeForLevel($level),
        );

        if($level < 4) {
            $config[] = 'book=off';
        }

        return implode(' ', $config);
    }

    protected function getTimeForLevel($level)
    {
        return 8 === $level ? 1 : round($level/10, 2);
    }
}
