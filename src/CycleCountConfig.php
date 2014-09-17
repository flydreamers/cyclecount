<?php
namespace CycleCount;

class CycleCountConfig implements
{
    /**
     * @var int the number of minutes to take care of
     */
    public $buckets = 10;

    /**
     * @var int
     */
    public $spanInSeconds = 60;

    /**
     * @var int the number of maximum request tu allow the user to have
     */
    public $maxRequests = 100;

    /**
     * @var string a key postfix
     */
    public $keyPostfix = '';

    public function CycleCountConfig($buckets)
}