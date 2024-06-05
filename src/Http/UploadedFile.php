<?php

namespace bru\api\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use function array_key_exists;
use function array_keys;
use function fclose;
use function fopen;
use function fwrite;
use function get_class;
use function gettype;
use function implode;
use function is_dir;
use function is_object;
use function is_resource;
use function is_string;
use function is_writable;
use function rename;
use function sprintf;

final class UploadedFile implements UploadedFileInterface
{
	private const ERRORS = [
		UPLOAD_ERR_OK => 'Файл загружен без ошибок.',
		UPLOAD_ERR_INI_SIZE => 'Загружаемый файл превышает параметр upload_max_filesize в php.ini.',
		UPLOAD_ERR_FORM_SIZE => 'Загружаемый файл превышает параметр MAX_FILE_SIZE который был указан в HTML форме.',
		UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично.',
		UPLOAD_ERR_NO_FILE => 'Файл не был загружен.',
		UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка.',
		UPLOAD_ERR_CANT_WRITE => 'Ошибка записи файла на диск.',
		UPLOAD_ERR_EXTENSION => 'Дополнение PHP остановило загрузку файла.',
	];

	/**
     * @var StreamInterface|null
	 */
    private StreamInterface|null $stream = null;

	/**
	 * @var string|null;
	 */
    private string|null $file;

	/**
	 * @var int
	 */
    private int $size;

	/**
	 * @var int
	 */
    private int $error;

	/**
	 * @var string|null;
	 */
    private string|null $clientFilename;

	/**
	 * @var string|null;
	 */
    private string|null $clientMediaType;

	/**
	 * @var bool
	 */
    private bool $isMoved = false;

	/**
	 * UploadedFile constructor.
	 * @param $streamOrFile
	 * @param int $size
	 * @param int $error
	 * @param string|null $clientFilename
	 * @param string|null $clientMediaType
	 */
	public function __construct(
		$streamOrFile,
		int $size,
		int $error,
        string|null $clientFilename = null,
        string|null $clientMediaType = null,
	)
	{
		if (!array_key_exists($error, self::ERRORS)) {
			throw new InvalidArgumentException(sprintf(
				'Неверный статус для загружаемого фала - "%s". Статус должен содержаться в константе "UPLOAD_ERR_*":  "%s".',
				$error,
				implode('", "', array_keys(self::ERRORS))
			));
		}

		$this->size = $size;
		$this->error = $error;
		$this->clientFilename = $clientFilename;
		$this->clientMediaType = $clientMediaType;

		if ($error !== UPLOAD_ERR_OK) {
			return;
		}

		if (is_string($streamOrFile)) {
			$this->file = $streamOrFile;
			return;
		}

		if (is_resource($streamOrFile)) {
			$this->stream = new Stream($streamOrFile);
			return;
		}

		if ($streamOrFile instanceof StreamInterface) {
			$this->stream = $streamOrFile;
			return;
		}

		throw new InvalidArgumentException(sprintf(
			'"%s" is not valid stream or file provided for "UploadedFile".',
			(is_object($streamOrFile) ? get_class($streamOrFile) : gettype($streamOrFile))
		));
	}

	/**
     * @return StreamInterface
	 */
    public function getStream(): StreamInterface
    {
		if ($this->error !== UPLOAD_ERR_OK) {
			throw new RuntimeException(self::ERRORS[$this->error]);
		}

		if ($this->isMoved) {
			throw new RuntimeException('Поток недоступен, так как был перемещен.');
		}

		if ($this->stream === null) {
			$this->stream = new Stream($this->file);
		}

		return $this->stream;
	}

	/**
	 * @param string $targetPath
	 */
    public function moveTo(string $targetPath): void
	{
		if ($this->error !== UPLOAD_ERR_OK) {
			throw new RuntimeException(self::ERRORS[$this->error]);
		}

		if ($this->isMoved) {
			throw new RuntimeException('Файл не может быть перемещен, так как он уже был перемещен ранее.');
		}

		if (empty($targetPath)) {
			throw new InvalidArgumentException('Путь недействителен для перемещения. Это должна быть непустая строка.');
		}

		$targetDirectory = dirname($targetPath);

		if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
			throw new RuntimeException(sprintf(
				'Требуемый каталог "%s" не существует либо недоступен для записи.',
				$targetDirectory
			));
		}

		$this->moveOrWriteFile($targetPath);
		$this->isMoved = true;
	}

	/**
	 * @return int|null
	 */
    public function getSize(): int|null
	{
		return $this->size;
	}

	/**
	 * @return int
	 */
	public function getError(): int
	{
		return $this->error;
	}

	/**
	 * @return string|null
	 */
    public function getClientFilename(): string|null
	{
		return $this->clientFilename;
	}

	/**
	 * @return string|null
	 */
    public function getClientMediaType(): string|null
	{
		return $this->clientMediaType;
	}

	/**
	 * @param string $targetPath
	 */
	private function moveOrWriteFile(string $targetPath): void
	{
		if ($this->file) {
            $isCliEnv = (!PHP_SAPI || str_starts_with(PHP_SAPI, 'cli') || str_starts_with(PHP_SAPI, 'phpdbg'));

			if (!($isCliEnv ? rename($this->file, $targetPath) : move_uploaded_file($this->file, $targetPath))) {
				throw new RuntimeException(sprintf('Uploaded file could not be moved to "%s".', $targetPath));
			}

			return;
		}

		if (!$file = fopen($targetPath, 'wb+')) {
			throw new RuntimeException(sprintf('Unable to write to "%s".', $targetPath));
		}

		$this->stream->rewind();

		while (!$this->stream->eof()) {
			fwrite($file, $this->stream->read(512000));
		}

		fclose($file);
	}
}