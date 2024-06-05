<?php

namespace bru\api;

use bru\api\Exceptions\SimpleFileCacheException;
use Psr\SimpleCache\CacheInterface;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_readable;
use function is_writable;
use function mkdir;
use function unlink;

final class SimpleFileCache implements CacheInterface
{

    /**
     * @var string
     * Домашняя директория библиотеки
     */
    private string $cachePath = __DIR__.DIRECTORY_SEPARATOR.'cache';

    /**
     * SimpleFileCache constructor.
     *
     * @throws SimpleFileCacheException
     */
    public function __construct()
    {
        if (!is_dir($this->cachePath) && !mkdir($this->cachePath)) {
            throw new SimpleFileCacheException('Невозможно создать директорию для хранения кэша /src/cache/');
        }
    }

    /**
     * @param string $key
     * @param null $default
     *
     * @return mixed
     * @throws SimpleFileCacheException
     */
    public function get(string $key, $default = null): mixed
    {
        $cacheFile = $this->cachePath.DIRECTORY_SEPARATOR.$key;

        //Нет прав для чтения
        if (!is_readable($cacheFile)) {
            throw new SimpleFileCacheException('Недостаточно прав для чтения кэша /src/cache/');
        }

        //Нет кеша с полученным ключом
        if (!file_exists($cacheFile)) {
            return false;
        }

        return file_get_contents($cacheFile);

    }

    /**
     * @param string $key
     * @param mixed $value
     * @param null $ttl
     *
     * @return bool
     * @throws SimpleFileCacheException
     */
    public function set(string $key, mixed $value, $ttl = null): bool
    {

        $cacheFile = $this->cachePath.DIRECTORY_SEPARATOR.$key;

        //Нет прав для записи
        if (!is_writable($this->cachePath)) {
            throw new SimpleFileCacheException('Недостаточно прав для записи кэша /src/cache/');
        }

        if (file_put_contents($cacheFile, $value)) return true;
        else return false;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key): bool
    {
        $cacheFile = $this->cachePath.DIRECTORY_SEPARATOR.$key;

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        return false;
    }

    public function clear(): bool
    {
        // TODO: Implement clear() method.
        return false;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        // TODO: Implement getMultiple() method.
        return [];
    }

    public function setMultiple($values, $ttl = null): bool
    {
        // TODO: Implement setMultiple() method.
        return false;
    }

    public function deleteMultiple($keys): bool
    {
        // TODO: Implement deleteMultiple() method.
        return false;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        $cacheFile = $this->cachePath.DIRECTORY_SEPARATOR.$key;

        if (file_exists($cacheFile)) return true;
        else return false;
    }
}