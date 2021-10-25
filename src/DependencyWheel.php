<?php
/**
 * Date: 10/25/21 11:46 PM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\DependentRunner;

use Redis;

class DependencyWheel
{

    private Redis $redis;
    private string $redisKey;

    public function __construct(DependencyConnection $connection)
    {
        $this->redis = $connection->getRedis();
        $this->redisKey = $connection->getRedisKey();
    }

    public function ready(): void
    {
        $this->redis->set($this->getRedisKey('state'), 1);
    }

    public function wait(): void
    {
        $this->redis->set($this->getRedisKey('state'), 0);
    }

    public function isDependentsStop(): bool
    {
        $set = $this->redis->sMembers($this->getRedisKey("set"));
        if (empty($set)) {
            return true;
        }

        foreach ($set as $id) {
            if ($this->redis->exists($this->getRedisKey("process:{$id}"))) {
                return false;
            }
        }

        return true;
    }

    protected function getRedisKey(string $key): string
    {
        return $this->redisKey . ':' . $key;
    }

}