<?php

namespace bru\api\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class Stream implements StreamInterface
{

    /**
     * @var resource|null
     */
    private mixed $resource;

    /**
     * Stream constructor.
     *
     * @param string $stream
     * @param string $mode
     */
    public function __construct(string $stream = 'php://temp', string $mode = 'wb+')
    {
        $stream = ($stream === '') ? false : @fopen($stream, $mode);

        if ($stream === false) {
            throw new RuntimeException('Невозможно открыть поток');
        }

        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException(
                'Поток должен быть передан в виде идентификатора потока или ресурса',
            );
        }

        $this->resource = $stream;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if ($this->resource) {
            $resource = $this->detach();
            fclose($resource);
        }
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        return $resource;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        if ($this->isSeekable()) {
            $this->rewind();
        }

        return $this->getContents();
    }

    /**
     * @return bool
     */
    public function isSeekable(): bool
    {
        return ($this->resource && $this->getMetadata('seekable'));
    }

    /**
     * @param null $key
     *
     * @return array|mixed|null
     */
    public function getMetadata($key = null): mixed
    {
        if (!$this->resource) {
            return $key ? null : [];
        }

        $metadata = stream_get_meta_data($this->resource);

        if ($key === null) {
            return $metadata;
        }

        if (array_key_exists($key, $metadata)) {
            return $metadata[$key];
        }

        return null;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * @param int $offset
     * @param int $whence
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->resource) {
            throw new RuntimeException('Нет ресурса для изменения позиции указателя');
        }

        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }

        if (fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('Error seeking within stream.');
        }
    }

    /**
     * @return false|string
     */
    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Невозможно прочитать данные из потока');
        }

        if (!is_string($result = stream_get_contents($this->resource))) {
            throw new RuntimeException('Error reading stream.');
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        if (!is_string($mode = $this->getMetadata('mode'))) {
            return false;
        }

        return (str_contains($mode, 'r') || str_contains($mode, '+'));
    }

    /**
     * @return int|null
     */
    public function getSize(): int|null
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);
        return isset($stats['size']) ? (int) $stats['size'] : null;
    }

    public function tell(): int
    {
        if (!$this->resource) {
            throw new RuntimeException('Нет ресурса для указания текущей позиции');
        }

        if (!is_int($result = ftell($this->resource))) {
            throw new RuntimeException('Ошибка определения позиции указателя');
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function eof(): bool
    {
        return (!$this->resource || feof($this->resource));
    }

    /**
     * @param string $string
     *
     * @return false|int
     */
    public function write(string $string): int
    {
        if (!$this->resource) {
            throw new RuntimeException('Нет ресурса для записи');
        }

        if (!$this->isWritable()) {
            throw new RuntimeException('Невозможно записать данные в поток');
        }

        if (!is_int($result = fwrite($this->resource, $string))) {
            throw new RuntimeException('Ошибка записи в поток');
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        if (!is_string($mode = $this->getMetadata('mode'))) {
            return false;
        }

        return (
            str_contains($mode, 'w') !== false
            || str_contains($mode, '+')
            || str_contains($mode, 'x') !== false
            || str_contains($mode, 'c') !== false
            || str_contains($mode, 'a') !== false
        );
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function read(int $length): string
    {
        if (!$this->resource) {
            throw new RuntimeException('Нет ресурса для чтения');
        }

        if (!$this->isReadable()) {
            throw new RuntimeException('Невозможно прочитать данные из потока');
        }

        if (!is_string($result = fread($this->resource, $length))) {
            throw new RuntimeException('Ошибка чтения из потока');
        }

        return $result;
    }
}