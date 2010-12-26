<?php

namespace Bundle\LichessBundle\Document;

/**
 * @mongodb:EmbeddedDocument
 */
class Clock
{
    /**
     * Maximum time of the clock per player
     *
     * @var int
     * @mongodb:Field(type="int")
     */
    private $limit = null;

    /**
     * Current player color
     *
     * @var string
     * @mongodb:Field(type="string")
     */
    private $color = null;

    /**
     * Times for white and black players
     *
     * @var array
     * @mongodb:Field(type="hash")
     */
    private $times = null;

    /**
     * Internal timer
     *
     * @var float
     * @mongodb:Field(type="float")
     */
    private $timer = null;

    /**
     *  Assume that a move takes some time to go from player1 -> server -> player2
     *  and remove this time from each move time
     */
    const HTTP_DELAY = 1;

    /**
     * Fisher clock bonus per move in seconds
     *
     * @var int
     * @mongodb:Field(type="int")
     */
    protected $increment;

    public function __construct($limit, $increment)
    {
        $this->limit = (int) $limit;
        $this->increment = (int) $increment;

        $this->reset();
    }

    /**
     * Name of the clock
     *
     * @return string
     **/
    public function getName()
    {
        return sprintf('%d minutes/side + %d seconds/move', round($this->limit / 60, 1), $this->increment);
    }

    /**
     * initializes the clock
     *
     * @return null
     **/
    public function reset()
    {
        $this->color = 'white';
        $this->times = array('white' => 0, 'black' => 0);
        $this->timer = null;
    }

    /**
     * Switch to next player
     *
     * @return null
     **/
    public function step()
    {
        if(!$this->isRunning()) {
            throw new \LogicException('Can not step clock as it is not running');
        }
        // Get absolute time
        $moveTime = microtime(true) - $this->timer;
        // Substract http delay
        $moveTime = max(0, $moveTime - static::HTTP_DELAY);
        // Substract move bonus
        $moveTime -= $this->increment;
        // Update player time
        $this->addTime($this->color, $moveTime);
        $this->color = 'white' === $this->color ? 'black' : 'white';
        $this->timer = microtime(true);
    }

    /**
     * Start the clock now
     *
     * @return null
     **/
    public function start()
    {
        $this->timer = microtime(true);
    }

    /**
     * Stop the clock now
     *
     * @return null
     **/
    public function stop()
    {
        if($this->isRunning()) {
            $this->addTime($this->color, microtime(true) - $this->timer);
            $this->timer = null;
        }
    }

    public function addTime($color, $time)
    {
        $this->times[$color] = round($this->times[$color] + $time, 2);
    }

    /**
     * Tell if the player with this color is out of tim
     *
     * @return boolean
     **/
    public function isOutOfTime($color)
    {
        return 0 === $this->getRemainingTime($color);
    }

    /**
     * Tell the time a player has to finish the game
     *
     * @return float
     **/
    public function getRemainingTime($color)
    {
        $time = $this->limit - $this->getElapsedTime($color);

        return max(0, round($time, 3));
    }

    /**
     * Tell the time a player has used
     *
     * @return float
     **/
    public function getElapsedTime($color)
    {
        $time = $this->times[$color];

        if($this->isRunning() && $color === $this->color) {
            $time += microtime(true) - $this->timer;
        }

        return round($time, 3);
    }

    public function getRemainingTimes()
    {
        return array(
            'white' => $this->getRemainingTime('white'),
            'black' => $this->getRemainingTime('black')
        );
    }

    /**
     * Get increment
     * @return int
     */
    public function getIncrement()
    {
        return $this->increment;
    }

    /**
     * Set increment
     * @param  int
     * @return null
     */
    public function setIncrement($increment)
    {
        $this->increment = (int) $increment;
    }

    /**
     * Get color
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Set color
     * @param  string
     * @return null
     */
    public function setColor($color)
    {
        $this->color = $color;
    }

    /**
     * Tell if the clock is enabled
     *
     * @return boolean
     **/
    public function isEnabled()
    {
        return $this->limit > 0;
    }

    /**
     * Tell if the clock is running
     *
     * @return boolean
     **/
    public function isRunning()
    {
        return null !== $this->timer;
    }

    /**
     * Get limit
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    public function getLimitInMinutes()
    {
        return round($this->getLimit()/60, 1);
    }

    /**
     * Get times
     * @return array
     */
    public function getTimes()
    {
        return $this->times;
    }

    public function renderTime($time)
    {
        return sprintf('%02d:%02d', floor($time/60), $time%60);
    }

    public function __clone()
    {
        $this->reset();
    }
}
