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
            return $options[$option] ?? null;
        }

        return $options;
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

    protected function _configure_device(string $device): void
    {
        $this->device = $device;

        // Call any configuration methods here, using $this->device, before the $device resource is opened.
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

        $this->_configure_device($device);

        $this->resource = \fopen($device, $mode);

        // The `$device` is now open for read/write access in binary. If not, i.e. it's `false`,
        if (false === $this->resource) {
            if (!$this->suppressErrors) {
                \trigger_error(\sprintf('Unable to fopen() device "%s".', $device), \E_ERROR);
            }

            return false;
        }

        // Make sure the target device is a tty.
        if (!\stream_isatty($this->resource)) {
            \fclose($this->resource);

            if (!$this->suppressErrors) {
                \trigger_error(\sprintf('Unable to fopen() device "%s". No TTY detected.', $device), \E_ERROR);
            }

            return false;
        }

        // Set the device handle to non-blocking IO.
        if (!\socket_set_blocking($this->resource, false)) {
            \fclose($this->resource);

            if (!$this->suppressErrors) {
                \trigger_error(\sprintf('Unable to fopen() device "%s" in non-blocking mode.', $device), \E_ERROR);
            }

            return false;
        }

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

    public function stream_close(): void
    {
        \fclose($this->resource);
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return false;
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
            throw new \Exception(\sprintf('Failed to register %s protocol', static::PROTOCOL_NAME));
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

        protected function _get_device(string $path): string
        {
            $device = parent::_get_device($path);

            if (1 === \preg_match("/^(?:com|COM)?(\d+)$/", $path, $matches)) {
                return \sprintf('com%d:', (int)$matches[1]);
            }

            // Just pass, if we cannot format it, probably a non-standard name.
            return $device;
        }

        protected function _configure_baud_rate(): void
        {
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

                return;
            }

            // Set the device baud rate, matching the one from Arduino.
            \shell_exec("mode {$this->device} baud={$baudRate}");
        }

        protected function _configure_parity(): void
        {
            $parity = (int)$this->_stream_context_options(static::CONTEXT_OPTION_PARITY);

            // Set the device parity.
            switch ($parity) {
                case -1: // NONE
                    \shell_exec("mode {$this->device} parity=n");

                    return;
                case  0: // EVEN
                    \shell_exec("mode {$this->device} parity=e");

                    return;
                case  1: // ODD
                    \shell_exec("mode {$this->device} parity=o");

                    return;
                default:
                    if ($this->suppressErrors) {
                        return;
                    }
            }

            \trigger_error(\sprintf('Unable to fopen() device "%s": Invalid parity option "%d".', $this->device,
                $parity), \E_ERROR);
        }

        protected function _configure_data_size(): void
        {
            $size = (int)$this->_stream_context_options(static::CONTEXT_OPTION_DATA_SIZE);

            // Set the device data size.
            $size = \min(8, \max(5, $size));

            \shell_exec("mode {$this->device} data={$size}");
        }

        protected function _configure_stop_size(): void
        {
            $size = (int)$this->_stream_context_options(static::CONTEXT_OPTION_STOP_SIZE);

            // Set the device stop bit size.
            $size = \min(2, \max(1, $size));

            \shell_exec("mode {$this->device} stop={$size}");
        }

        protected function _configure_command(): void
        {
            $command = $this->_stream_context_options(static::CONTEXT_OPTION_COMMAND);

            if (null === $command) {
                return;
            }

            $command = \escapeshellcmd($command);

            \shell_exec("mode {$this->device} {$command}");
        }

        /**
         * For `mode` information
         *  see docs at https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/mode.
         */
        protected function _configure_device(string $device): void
        {
            parent::_configure_device($device);

            $this->_configure_baud_rate();
            $this->_configure_parity();
            $this->_configure_data_size();
            $this->_configure_stop_size();

            // Set the device flow control to "none", setting it is not supported by this implementation.
            \shell_exec("mode {$device} xon=off octs=off rts=on");

            $this->_configure_command();
        }

        /**
         * @return resource
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

        protected function _get_device(string $path): string
        {
            return \sprintf('/dev/%s', parent::_get_device($path));
        }

        protected function _configure_baud_rate(): void
        {
            $baudRate = (int)$this->_stream_context_options(static::CONTEXT_OPTION_BAUD_RATE);

            // Set the device baud rate, matching the one from Arduino.
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
                    $f = (\PHP_OS_FAMILY === 'Darwin') ? '-f' : '-F';
                    \shell_exec("stty $f '{$this->device}' {$baudRate}");

                    return;
                default:
                    if ($this->suppressErrors) {
                        return;
                    }
            }

            \trigger_error(\sprintf('Unable to fopen() device "%s": Invalid baud rate option "%d".', $this->device,
                $baudRate), \E_ERROR);
        }

        protected function _configure_parity(): void
        {
            $parity = (int)$this->_stream_context_options(static::CONTEXT_OPTION_PARITY);

            $f = (\PHP_OS_FAMILY === 'Darwin') ? '-f' : '-F';
            // Set the device parity.
            switch ($parity) {
                case -1: // NONE
                    \shell_exec("stty $f '{$this->device}' -parenb");

                    return;
                case  0: // EVEN
                    \shell_exec("stty $f '{$this->device}'  parenb -parodd");

                    return;
                case  1: // ODD
                    \shell_exec("stty $f '{$this->device}'  parenb  parodd");

                    return;
                default:
                    if ($this->suppressErrors) {
                        return;
                    }
            }

            \trigger_error(\sprintf('Unable to fopen() device "%s": Invalid parity option "%d".', $this->device,
                $parity), \E_ERROR);
        }

        protected function _configure_data_size(): void
        {
            $size = (int)$this->_stream_context_options(static::CONTEXT_OPTION_DATA_SIZE);

            // Set the device data size.
            $size = \min(8, \max(5, $size));

            $f = (\PHP_OS_FAMILY === 'Darwin') ? '-f' : '-F';
            \shell_exec("stty $f '{$this->device}' cs{$size}");
        }

        protected function _configure_stop_size(): void
        {
            $size = (int)$this->_stream_context_options(static::CONTEXT_OPTION_STOP_SIZE);

            // Set the device stop bit size.
            $size = \min(2, \max(1, $size));

            $f = (\PHP_OS_FAMILY === 'Darwin') ? '-f' : '-F';
            if (1 < $size) {
                \shell_exec("stty $f '{$this->device}'  cstopb");
            } else {
                \shell_exec("stty $f '{$this->device}' -cstopb");
            }
        }

        protected function _configure_command(): void
        {
            $command = $this->_stream_context_options(static::CONTEXT_OPTION_COMMAND);

            if (null === $command) {
                return;
            }

            $command = \escapeshellcmd($command);

            $f = (\PHP_OS_FAMILY === 'Darwin') ? '-f' : '-F';
            \shell_exec("stty $f '{$this->device}' {$command}");
        }

        /**
         * For `stty` information, see man pages at https://man7.org/linux/man-pages/man1/stty.1.html.
         */
        protected function _configure_device(string $device): void
        {
            parent::_configure_device($device);

            $this->_configure_baud_rate();
            $this->_configure_parity();
            $this->_configure_data_size();
            $this->_configure_stop_size();

            $f = (\PHP_OS_FAMILY === 'Darwin') ? '-f' : '-F';
            // Set the device flow control to "none", setting it is not supported by this implementation.
            \shell_exec("stty $f '{$device}' clocal -crtscts -ixon -ixoff");

            $this->_configure_command();
        }

        /**
         * @return resource
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
