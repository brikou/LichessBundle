<?php

namespace Bundle\LichessBundle\Form;

use Symfony\Component\Form\Form;

use Symfony\Component\Form\HiddenField;
use Symfony\Component\Form\TextField;

abstract class GameConfigFormWithColor extends GameConfigForm
{
    protected $hasHiddenColor = false;
    protected $possibleColors = array('white', 'black', 'random');
    protected $defaultColor   = 'random';

    public function configure()
    {
        $this->add(new TextField('color'));
    }

    public function addColorHiddenField()
    {
        $this->remove('color');
        $this->add(new HiddenField('color'));
        $this->hasHiddenColor = true;
    }

    public function hasHiddenColor()
    {
        return $this->hasHiddenColor;
    }

    public function submit($data)
    {
        if (!in_array($data['color'], $this->possibleColors)) {
            if ($this->logger) {
                $this->logger->warn(sprintf('%s: Invalid color submitted "%s" by %s', get_class($this), $data['color'], $_SERVER['HTTP_USER_AGENT']));
            }
            $data['color'] = $this->defaultColor;
        }

        return parent::submit($data);
    }
}
