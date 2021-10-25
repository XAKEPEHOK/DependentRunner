<?php
/**
 * Date: 10/26/21 12:07 AM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\DependentRunner;

use Redis;

class DependencyConnection
{
    private Redis $redis;
    private string $redisKey;

    public function __construct(Redis $redis, string $redisKey)
    {
        $this->redis = $redis;
        $this->redisKey = $redisKey;
    }

    public function getRedis(): Redis
    {
        return $this->redis;
    }

    public function getRedisKey(): string
    {
        return $this->redisKey;
    }

}