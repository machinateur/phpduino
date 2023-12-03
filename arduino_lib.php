<?php

declare(strict_types=1);

namespace Machinateur\Arduino;

interface streamWrapperInterface
{
    function stream_open(
        string $path,
        string $mode,
        int $options,
        ?string &$opened_path,
    ): bool;
    function stream_read(int $count): string|false;
    function stream_write(string $data): int;
    function stream_eof(): bool;
    function stream_flush(): bool;
    function stream_close(): void;
}

abstract class streamWrapperAbstract implements streamWrapperInterface
{
    public const PROTOCOL_NAME = 'arduino';

    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var resource
     */
    public $context;

    protected bool $suppressErrors = true;

    protected readonly string $device;

    /**
     * @return mixed|array|null
     */
    protected function _stream_context_options(?string $option = null, bool $use_resource = false): mixed
    {
        $options = \stream_context_get_options($use_resource ? $this->resource : $this->context);

        if (null !== $option) {
            return $options[static::PROTOCOL_NAME][$option] ?? null;
        }

        return $options[static::PROTOCOL_NAME] ?? [];
    }

    protected function _get_device(string $path): string
    {
        $protocol = static::PROTOCOL_NAME;
        $protocol = \preg_quote("{$protocol}://", '/');

        if (1 === \preg_match("/^(?:{$protocol})?(.+)$/", $path, $matches)) {
            return $matches[1];
        }

        return $path;
    }

    /**
     * @return resource|false
     */
    protected function _configure_device(
        string $device,
        string $mode = 'r+b',
    )/*: resource*/
    {
        // Call any configuration methods here, before or after the $device resource is opened.

        return \fopen($device, $mode);
    }

    protected function _init(
        string $path,
        string $mode = 'r+b',
    ): bool
    {
        // The device's full path.
        $device = $this->_get_device($path);

        if (!\file_exists($device)) {
            if (!$this->suppressErrors) {
                \trigger_error(\sprintf('Unable to fopen() device "%s". The file does not exist!', $device), \E_ERROR);
            }

            return false;
        }

        $resource = $this->_configure_device($device, $mode);

        // The `$device` is now open for read/write access in binary. If not, i.e. it's `false`,
        if (false === $resource) {
            if (!$this->suppressErrors) {
                \trigger_error(\sprintf('Unable to fopen() device "%s".', $device), \E_ERROR);
            }

            return false;
        }

        $this->resource = $resource;
        $this->device   = $device;

        return true;
    }

    public function stream_open(
        string  $path,
        string  $mode,
        int     $options,
        ?string &$opened_path,
    ): bool
    {
        $this->suppressErrors = !($options & \STREAM_REPORT_ERRORS);

        if ($options & \STREAM_USE_PATH && !$this->suppressErrors) {
            \trigger_error('The "include_path" capability is not implemented!', \E_WARNING);
        }

        return $this->_init($path, $mode);
    }

    public function stream_read(int $count): string|false
    {
        return \fread($this->resource, $count);
    }

    public function stream_write(string $data): int
    {
        return \fwrite($this->resource, $data);
    }

    public function stream_eof(): bool
    {
        return \feof($this->resource);
    }

    public function stream_flush(): bool
    {
        return \fflush($this->resource);
    }

    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool
    {
        return 0 === \fseek($this->resource, $offset, $whence);
    }

    public function stream_tell(): int
    {
        return \ftell($this->resource);
    }

    public function stream_truncate(int $newSize): bool
    {
        return \ftruncate($this->resource, $newSize);
    }

    public function stream_close(): void
    {
        \fclose($this->resource);
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return false;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2 = 0): bool
    {
        return match ($option) {
            \STREAM_OPTION_BLOCKING     => \stream_set_blocking($this->resource, (bool)$arg1),
            \STREAM_OPTION_READ_TIMEOUT => \stream_set_timeout($this->resource, $arg1, $arg2),
            \STREAM_OPTION_READ_BUFFER  => \stream_set_read_buffer($this->resource, $arg2),
            \STREAM_OPTION_WRITE_BUFFER => \stream_set_write_buffer($this->resource, $arg2),
            default                     => false,
        };
    }

    public function stream_stat(): array|false
    {
        return \fstat($this->resource);
    }

    /**
     * @return resource
     * @throws \Exception
     */
    public static function register(array $defaults)
    {
        if (static::class === self::class) {
            throw new \LogicException(\sprintf('The %s has to be overridden by a concrete implementation'
                . ' and may not be called on %s directly.', __METHOD__, self::class));
        }

        if (\in_array(static::PROTOCOL_NAME, \stream_get_wrappers(), true)) {
            \stream_wrapper_unregister(static::PROTOCOL_NAME);
        }

        $registered = \stream_wrapper_register(static::PROTOCOL_NAME, static::class);
        if (!$registered) {
            throw new \UnexpectedValueException(\sprintf('Failed to register %s protocol', static::PROTOCOL_NAME));
        }

        return \stream_context_set_default([
            static::PROTOCOL_NAME => $defaults,
        ]);
    }
}

if ('Windows' === \PHP_OS_FAMILY) {
    class streamWrapper extends streamWrapperAbstract
    {
        protected const CONTEXT_OPTION_BAUD_RATE = 'baud_rate';
        protected const CONTEXT_OPTION_PARITY    = 'parity';
        protected const CONTEXT_OPTION_DATA_SIZE = 'data_size';
        protected const CONTEXT_OPTION_STOP_SIZE = 'stop_size';
        protected const CONTEXT_OPTION_COMMAND   = 'custom_command';
        protected const CONTEXT_OPTION_USLEEP_S  = 'usleep_s';

        protected function _get_device(string $path): string
        {
            $device = parent::_get_device($path);

            if (1 === \preg_match("/^(?:com|COM)?(\d+)$/", $device, $matches)) {
                return \sprintf('com%d', (int)$matches[1]);
            }

            // Just pass, if we cannot format it, probably a non-standard name.
            return $device;
        }

        /**
         * @return \Generator<string>
         */
        protected function _get_command(): \Generator
        {
            // Set the custom command. If so, no further command parts are yielded.
            $command = $this->_stream_context_options(static::CONTEXT_OPTION_COMMAND);
            if (null !== $command) {
                yield from (array)$command;

                return;
            }

            // Set the device baud rate, matching the one from Arduino.
            $baudRate = (int)$this->_stream_context_options(static::CONTEXT_OPTION_BAUD_RATE);
            // Windows baud mapping, looks a bit weird.
            $baudRate = match ($baudRate) {
                    110 =>      11,
                    150 =>      15,
                    300 =>      30,
                    600 =>      60,
                  1_200 =>      12,
                  2_400 =>      24,
                  4_800 =>      48,
                  9_600 =>      96,
                 19_200 =>      19,
                 38_400 =>  38_400,
                 57_600 =>  57_600,
                115_200 => 115_200,
                default =>       0,
            };

            if (0 === $baudRate) {
                if (!$this->suppressErrors) {
                    \trigger_error(\sprintf('Unable to fopen() device "%s": Invalid baud rate option "%d".',
                        $this->device, $baudRate), \E_ERROR);
                }
            } else {
                yield "baud={$baudRate}";
            }

            // Set the device parity.
            $parity = (int)$this->_stream_context_options(static::CONTEXT_OPTION_PARITY);
            switch ($parity) {
                case -1: // NONE
                    yield 'parity=n';
                    break;
                case  0: // EVEN
                    yield 'parity=e';
                    break;
                case  1: // ODD
                    yield 'parity=o';
                    break;
                default:
                    if (!$this->suppressErrors) {
                        \trigger_error(\sprintf('Unable to fopen() device "%s": Invalid parity option "%s".',
                            $this->device, $parity), \E_ERROR);
                    }
            }

            // Set the device data size.
            $dataSize = (int)$this->_stream_context_options(static::CONTEXT_OPTION_DATA_SIZE);
            $dataSize = \min(8, \max(5, $dataSize));

            yield "data={$dataSize}";

            // Set the device stop bit size.
            $stopSize = (int)$this->_stream_context_options(static::CONTEXT_OPTION_STOP_SIZE);
            $stopSize = \min(2, \max(1, $stopSize));

            yield "stop={$stopSize}";

            // Set the device flow control to "none", setting it is not supported by this implementation.
            yield 'xon=off';
            yield 'octs=off';
            yield 'rts=on';
        }

        /**
         * For `mode` information
         *  see docs at https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/mode.
         *
         * @return resource|false
         */
        protected function _configure_device(
            string $device,
            string $mode = 'r+b',
        )/*: resource*/
        {
            $command = "mode {$device}";
            foreach ($this->_get_command() as $part) {
                $command .= " {$part}";
            }

            // This is the sleep time in seconds, applied as multiplier to usleep() with 1mil as base.
            $sleepTime = (float)$this->_stream_context_options(static::CONTEXT_OPTION_USLEEP_S);
            $sleepTime = (float)\max(1.0, (float)\abs($sleepTime));

            // On windows, configure the port...
            \shell_exec($command);
            // ... before opening the stream. This is when the device becomes unavailable for configuration changes.
            $resource = parent::_configure_device($device, $mode);
            // ... wait a carefully measured amount of time...
            \usleep((int)($sleepTime * 1_000_000));
            // Doing some housekeeping, these will have no visible effect on windows.
            \stream_set_timeout($resource, 0);
            \stream_set_read_buffer($resource, 0);
            \stream_set_write_buffer($resource, 0);

            return $resource;
        }

        protected function _init(
            string $path,
            string $mode = 'r+b',
        ): bool
        {
            if (!parent::_init($path, $mode)) {
                return false;
            }

            // On windows, do no validation on the return value, which seems rather unpredictable.
            \stream_set_blocking($this->resource, false);

            return true;
        }

        /**
         * @return resource
         * @throws \Exception
         */
        public static function register(array $defaults = [])
        {
            return parent::register(
                \array_merge([
                    static::CONTEXT_OPTION_BAUD_RATE =>   96,
                    static::CONTEXT_OPTION_PARITY    =>   -1,
                    static::CONTEXT_OPTION_DATA_SIZE =>    8,
                    static::CONTEXT_OPTION_STOP_SIZE =>    1,
                    static::CONTEXT_OPTION_COMMAND   => null,
                    static::CONTEXT_OPTION_USLEEP_S  =>    2,
                ],  $defaults)
            );
        }
    }
} else {
    class streamWrapper extends streamWrapperAbstract
    {
        protected const CONTEXT_OPTION_BAUD_RATE = 'baud_rate';
        protected const CONTEXT_OPTION_PARITY    = 'parity';
        protected const CONTEXT_OPTION_DATA_SIZE = 'data_size';
        protected const CONTEXT_OPTION_STOP_SIZE = 'stop_size';
        protected const CONTEXT_OPTION_COMMAND   = 'custom_command';
        protected const CONTEXT_OPTION_USLEEP_S  = 'usleep_s';

        protected function _get_device(string $path): string
        {
            return \sprintf('/dev/%s', parent::_get_device($path));
        }

        /**
         * @return \Generator<string>
         */
        protected function _get_command(): \Generator
        {
            // Set the custom command. If so, no further command parts are yielded.
            $command = $this->_stream_context_options(static::CONTEXT_OPTION_COMMAND);
            if (null !== $command) {
                yield from (array)$command;

                return;
            }

            // Set the device baud rate, matching the one from Arduino.
            $baudRate = (int)$this->_stream_context_options(static::CONTEXT_OPTION_BAUD_RATE);
            switch ($baudRate) {
                case     300:
                case     600:
                case   1_200:
                case   2_400:
                case   4_800:
                case   9_600:
                case  14_400:
                case  19_200:
                case  28_800:
                case  31_250:
                case  38_400:
                case  57_600:
                case 115_200:
                    yield "{$baudRate}";
                    break;
                default:
                    if (!$this->suppressErrors) {
                        \trigger_error(\sprintf('Unable to fopen() device "%s": Invalid baud rate option "%d".',
                            $this->device, $baudRate), \E_ERROR);
                    }
            }

            // Set the device parity.
            $parity = (int)$this->_stream_context_options(static::CONTEXT_OPTION_PARITY);
            switch ($parity) {
                case -1: // NONE
                    yield '-parenb';
                    break;
                case  0: // EVEN
                    yield 'parenb';
                    yield '-parodd';
                    break;
                case  1: // ODD
                    yield 'parenb';
                    yield 'parodd';
                    break;
                default:
                    if (!$this->suppressErrors) {
                        \trigger_error(\sprintf('Unable to fopen() device "%s": Invalid parity option "%d".',
                            $this->device, $parity), \E_ERROR);
                    }
            }

            // Set the device data size.
            $dataSize = (int)$this->_stream_context_options(static::CONTEXT_OPTION_DATA_SIZE);
            $dataSize = \min(8, \max(5, $dataSize));

            yield "cs{$dataSize}";

            // Set the device stop bit size.
            $size = (int)$this->_stream_context_options(static::CONTEXT_OPTION_STOP_SIZE);
            $size = \min(2, \max(1, $size));

            if (1 < $size) {
                yield 'cstopb';
            } else {
                yield '-cstopb';
            }

            // Set the device flow control to "none", setting it is not supported by this implementation.
            yield 'clocal';
            yield '-crtscts';
            yield '-ixon';
            yield '-ixoff';
        }

        /**
         * For `stty` information, see man pages at https://man7.org/linux/man-pages/man1/stty.1.html.
         *
         * @return resource|false
         */
        protected function _configure_device(
            string $device,
            string $mode = 'r+b',
        )/*: resource*/
        {
            $f = (\PHP_OS_FAMILY === 'Darwin') ? '-f' : '-F';

            $command = "stty $f '{$device}'";
            foreach ($this->_get_command() as $part) {
                $command .= " {$part}";
            }

            // This is the sleep time in seconds, applied as multiplier to usleep() with 1mil as base.
            $sleepTime = (float)$this->_stream_context_options(static::CONTEXT_OPTION_USLEEP_S);
            $sleepTime = (float)\max(1.0, (float)\abs($sleepTime));

            // On linux, open the stream first, ...
            $resource = parent::_configure_device($device);
            \stream_set_read_buffer($resource, 0);
            \stream_set_write_buffer($resource, 0);
            // ... wait a carefully measured amount of time...
            \usleep((int)($sleepTime * 1_000_000));
            // ... and then configure it. This is because on UNIX systems, ports may reset when unused.
            \shell_exec($command);
            // This way, we prevent the configuration from resetting unless our handle is released first.
            return $resource;
        }

        protected function _init(
            string $path,
            string $mode = 'r+b',
        ): bool
        {
            if (!parent::_init($path, $mode)) {
                return false;
            }

            // Make sure the target device is a tty.
            if (!\stream_isatty($this->resource)) {
                \fclose($this->resource);

                if (!$this->suppressErrors) {
                    \trigger_error(\sprintf('Unable to fopen() device "%s". No TTY detected.',
                        $this->device), \E_ERROR);
                }

                return false;
            }

            // Set the device handle to non-blocking IO.
            if (!\stream_set_blocking($this->resource, false)) {
                \fclose($this->resource);

                if (!$this->suppressErrors) {
                    \trigger_error(\sprintf('Unable to fopen() device "%s" in non-blocking mode.',
                        $this->device), \E_ERROR);
                }

                return false;
            }

            return true;
        }

        /**
         * @return resource
         * @throws \Exception
         */
        public static function register(array $defaults = [])
        {
            return parent::register(
                \array_merge([
                    static::CONTEXT_OPTION_BAUD_RATE => 9600,
                    static::CONTEXT_OPTION_PARITY    =>   -1,
                    static::CONTEXT_OPTION_DATA_SIZE =>    8,
                    static::CONTEXT_OPTION_STOP_SIZE =>    1,
                    static::CONTEXT_OPTION_COMMAND   => null,
                    static::CONTEXT_OPTION_USLEEP_S  =>    1.618119,
                ],  $defaults)
            );
        }
    }
}

/**
 * @param array<int> $bytes
 */
function byte_pack(array $bytes): string|false
{
    return [] === $bytes ? '' : \pack('C*', ...\array_values($bytes));
}

/**
 * @return array<int>|false
 * @throws \UnexpectedValueException
 */
function byte_unpack(string $binaryData): array|false
{
    $bytes = \unpack('C*', $binaryData);
    return false === $bytes ? false : \array_values($bytes);
}
