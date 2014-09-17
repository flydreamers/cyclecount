<?php

namespace CycleCount;

/**
 * Class HttpRateLimitter
 * Allow to limit the amount of request by user
 */
class CycleCount
{

    /**
     * @var \CycleCount\CycleCountPersistence
     */
    protected static $persister = null;

    /**
     * @return \CycleCount\CycleCountPersistence
     */
    public static function getPersister()
    {
        return self::$persister;
    }

    /**
     * @param \CycleCount\CycleCountPersistence $persister
     */
    public static function setPersister($persister)
    {
        if ($persister instanceof CycleCountPersistence){
            throw new CycleCountException("The persister that you are trying to use is invalid");
        }
        self::$persister = $persister;
    }

    /**
     * @var CycleCount[]
     */
    private static $instances = [];

    /**
     * Get CycleCount instance $instanceName that use $persister CycleCountPersistence
     * @param $instanceName string The name of the instance
     * @param $persister CycleCountPersistence The Persister of the instance
     * @return CycleCount
     */
    public static function getCycleCountInstance($instanceName, $persister)
    {
        if (!isset(self::$instances[$instanceName])){
            self::$instances[$instanceName] = new self($persister);
        }
        return self::$instances[$instanceName];
    }


    /**
     * Try if the user can add a new request, if it can, it will add it
     * If it can not return false
     * @param $idUser int the id user to add a request to
     * @param $timestamp int the timestamp of the request
     * @return bool if the request was allow or not
     */
    public function allowRequest($key, $timestamp)
    {
        $key = $this->transformKey($key);
        $lastRequests = CJSON::decode($this->redis->get($key));
        if (!is_null($lastRequests)) {
            $lastRequests = $this->removeOldRequest($timestamp, $lastRequests);
        } else {
            $lastRequests = $this->createRequestStructure();
        }
        $ret = $this->checkRequestLimit($lastRequests);
        $lastRequests = $this->addNewRequest($timestamp, $lastRequests);
        $this->redis->set($key, CJSON::encode($lastRequests));
        return $ret;
    }

    /**
     * Removes all the request that are old enough to count
     * If the time between now and the las request if bigger than the $minutes
     * time, all the request will be reset.
     * @param $newRequestTimestamp int
     * @param $lastRequests array a LastRequest data structure
     * @return array the new LastRequest data structure
     */
    protected function removeOldRequest($newRequestTimestamp, $lastRequests)
    {
        if ($lastRequests['lastTime'] + $this->minutes * 60 < $newRequestTimestamp) {
            $last = $lastRequests['lastTime'];
            $lastRequests = $this->createRequestStructure();
            $lastRequests['lastTime'] = $last;
            return $lastRequests;
        }
        $i = $newBucket = floor($newRequestTimestamp / 60) % $this->minutes;
        $j = $lastBucket = floor($lastRequests['lastTime'] / 60) % $this->minutes;
        if ($i > $j) {
            $tmp = $i;
            $i = $j;
            $j = $tmp;
        }
        while (++$i < $j) {
            $lastRequests[$i] = 0;
        }
        return $lastRequests;
    }

    /**
     * Check if the request qty in the las period of time is less done the maximum
     * @param $lastRequests
     * @return bool
     */
    protected function checkRequestLimit($lastRequests)
    {
        return array_sum($lastRequests['requests']) <= $this->maxRequests;
    }

    /**
     * Add a new request to the LastRequest data
     * @param $timestamp int the time of the request
     * @param $lastRequests array a LastRequest data structure to add the request
     * @return array a LastRequest data structure with the request added
     */
    protected function addNewRequest($timestamp, $lastRequests)
    {
        $bucket = floor($timestamp / 60) % $this->minutes;
        $lastRequests[$bucket]++;
        $lastRequests['lastTime'] = $timestamp;
        return $lastRequests;
    }

    /**
     * Create an empty LastRequest data structure
     * @return array a LastRequest data structure
     */
    protected function createRequestStructure()
    {
        $requests = [];
        for ($i = 0; $i < $this->minutes; $i++) {
            $requests[$i] = 0;
        }
        return [
            'lastTime' => null,
            'requests' => $requests
        ];
    }


    /**
     * The redis internal key for the request user
     * @param $idUser int
     * @return string
     */
    protected function transformKey($key)
    {
        return 'Requests::' . $key . $this->keyPostfix;
    }
}
