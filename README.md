# phpduino

A user-land stream wrapper implementation for PHP to Arduino communication via USB serial.

## Concept

This package defines an `arduino://` protocol handler using
 a [`streamWrapper`](https://www.php.net/manual/en/class.streamwrapper.php) implementation.
It also provides some byte processing utility functions (`byte_pack()` and `byte_unpack()`).

Please note that this library is still in development, consider it unstable until version `1.0`.
 Until then, pretty much anything may change without notice, even with patch releases.

## Requirements

This package requires at least PHP 8.1 to work. No dependencies.

The [Arduino IDE](https://www.arduino.cc/en/software) is still required to upload sketches to the connected device.

## Usage

```php
// First register the protocol and stream wrapper, ...
\Machinateur\Arduino\streamWrapper::register();
// ... then use the protocol in a stream function...
$fd = \fopen('arduino://'.$deviceName, 'r+b');
// ... and finally do things with the $fd (fread()/fwrite() ops).
//  See `./example/echo` for some simple examples.
```

You can supply options to the connection, using a stream context:

<details>
  <summary>Click here for parameter explanation for the options below.</summary>

  ```php
  /**
   * The device's name in `/dev/`.
   *  Prefer `cu` if on Mac, use `ls /dev/tty.*` to find the available devices.
   *
   * On windows, use the device manager to identify the correct serial (com) port, under the `COM` device group.
   */
  if ('Darwin' === \PHP_OS_FAMILY) {
      $deviceName = 'tty.usbmodem2101';
      $deviceName = 'cu.usbmodem2101';
  } elseif ('Windows' === \PHP_OS_FAMILY) {
      $deviceName = 'COM7';
  } else {
      $deviceName = '';
  
      \trigger_error(
          \sprintf('No device name specified in "%s" on line "%d"!', __FILE__, __LINE__ - 3), \E_USER_ERROR);
  }
  
  /**
   * The device's baud rate.
   *
   * See Arduino docs at https://docs.arduino.cc/learn/built-in-libraries/software-serial#begin for conventional rates.
   *
   * Supported baud rates are:
   *  - ` 300`
   *  - ` 600`
   *  - ` 1200`
   *  - ` 2400`
   *  - ` 4800`
   *  - ` 9600`
   *  - ` 14400`
   *  - ` 19200`
   *  - ` 28800`
   *  - ` 31250`
   *  - ` 38400`
   *  - ` 57600`
   *  - `115200`
   */
  $deviceBaudRate = 9600;
  
  /**
   * The device's parity bit configuration.
   *
   * See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
   * - `none` = `-1`
   * - ` odd` = ` 1`
   * - `even` = ` 0`
   *
   * Default is `SERIAL_8N1`, so `N` is the data size.
   */
  $deviceParity   = -1;
  
  /**
   * The device's data size configuration.
   *
   * See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
   *
   * Default is `SERIAL_8N1`, so `8` is the data size.
   */
  $deviceDataSize = 8;
  
  /**
   * The device's stop bit size configuration.
   *
   * See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
   *
   * Default is `SERIAL_8N1`, so `1` is the stop bit size.
   */
  $deviceStopSize = 1;
  
  /**
   * The device custom command. If set, will be yielded in favor of default commands.
   *
   * Try to stop the reset on `fclose()`, see https://stackoverflow.com/a/59549518.
   * - `[-]hup` = send a hangup signal when the last process closes the tty
   *
   * Ideally only run with the `-hup` option if not yet set, so there will be no more restarts due to RTS HANGUP.
   *  Only use if desired.
   */
  $deviceCustomCommand = null;
  ```
</details>

```php
// The stream context configuration.
$context = \stream_context_create([
    \Machinateur\Arduino\streamWrapper::PROTOCOL_NAME => [
        'baud_rate'      => $deviceBaudRate,
        'parity'         => $deviceParity,
        'data_size'      => $deviceDataSize,
        'stop_size'      => $deviceStopSize,
        'custom_command' => $deviceCustomCommand,
        // A safe threshold for the arduino to boot on `fopen()`.
        'usleep_s'       => 2,
    ],
]);

$fd = \fopen('arduino://'.$deviceName, 'r+b', $context);
```

## Installation

```
composer require machinateur/phpduino
```

## Docs

### Relevant links

<details>
  <summary>Here are some links to relevant documentation, articles and forum threads... (click)</summary>

  - https://docs.arduino.cc/learn/built-in-libraries/software-serial#begin
  - https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/
  - https://unix.stackexchange.com/a/138390
  - https://stackoverflow.com/a/8632603
  - https://playground.arduino.cc/Interfacing/LinuxTTY/
  - https://forum.arduino.cc/t/linux-serial-io/38934/2
  - https://web.archive.org/web/20110228183102/https://anealkhimani.com/2010/02/08/web-enabled-pantilt-webcam-with-arduino-and-php-part-1/
  - https://web.archive.org/web/20110217155443/http://anealkhimani.com/2010/02/20/web-enabled-pantilt-web-came-with-arduino-and-php-part-2/
  - https://web.archive.org/web/20110217070336/http://anealkhimani.com/2010/02/21/web-enabled-pantilt-camera-with-arduino-and-php-part-3/
  - https://github.com/Xowap/PHP-Serial/blob/develop/src/PhpSerial.php
  - https://man7.org/linux/man-pages/man1/stty.1.html
  - https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/mode
  - https://unix.stackexchange.com/questions/242778/what-is-the-easiest-way-to-configure-serial-port-on-linux
  - https://www.php.net/manual/en/class.streamwrapper.php
  - https://stackoverflow.com/a/9616217
  - https://stackoverflow.com/a/59549518
  - https://stackoverflow.com/questions/32569611/linux-stty-command-lag-help-needed-on-serial-usb
  - https://forum.arduino.cc/t/arduino-auto-resets-after-opening-serial-monitor/850915
  - https://forum.arduino.cc/t/using-php-to-control-the-arduino-over-usb-serial-connection/134478/9
  - https://raspberrypi.stackexchange.com/questions/36490/stty-command-lag-and-queue-issue
  - https://raspberrypi.stackexchange.com/questions/9695/disable-dtr-on-ttyusb0
  - https://stackoverflow.com/a/957416
</details>

### Sleep timer

That is, the time to sleep before opening the device handle in seconds. The option in the stream context is `usleep_s`.

For windows, this timeout is applied before the device is opened internally by the stream wrapper, but after it was
 configured, while on Mac / linux the timeout occurs after the device has been opened internally, but before it was
 configured using the command.

In my tests, I could go as low as `1.618119`, but below that the success rate started to drop randomly. The cable and
 port used could also play a role here.

The timeout is required, as on linux the handle has to be occupied, to avoid a premature reset of the configuration.
And likewise, on windows, the configuration can only be applied before the handle is occupied, hence the device
 is busy after that.

### Stream chunk size

As it turns out, this is the key, as for serial (com) ports on windows represented as file stream:

> If the stream is read buffered, and it does not represent a plain file, at most one read of up to a number of bytes
>  equal to the chunk size (usually `8192`) is made; depending on the previously buffered data, the size of the returned
>  data may be larger than the chunk size.

This undocumented behaviour of the streamWrapper API may be observed when removing this call and monitoring the value
 of `$count` in `streamWrapperAbstract::stream_read()` when called during `\fread($fd, 1)`. The `$count` will be `8192`!

```
\stream_set_chunk_size($fd, 1);
```

Due to the always-blocking nature of streams on windows, you'll have to use `\stream_select()` to determine if there is
 more data to read or write. See [the docs](https://www.php.net/manual/en/function.stream-select.php) for instructions.
 Otherwise, the read operation could block indefinitely, as it seems, given the device does not send any data.

So any script reading responses will have to be very careful, to avoid blocking calls.

### Auto-restart on connect

According to several sources it is possible to disable DTR (Data Transmission Ready) line reset, which apparently is a
 hangup signal. In my tests I couldn't make these options work on neither windows nor mac.

For applications which require accurate and non-resetting continuous functionality,
 it might be an option [to use a hardware solution](https://raspberrypi.stackexchange.com/a/22196) to secure that.

## Example

You can find an easy example that works with the below code in `./example/echo` on Mac.

There is also another more complex example included, involving binary data transmission,
 which can be found in `./example/echo/echo-binary.php`.

### Arduino sketch

```c
byte incomingByte = 0;

void setup()
{
    Serial.begin(9600, SERIAL_8N1);
}

void loop()
{
  if (Serial.available() > 0) {
    incomingByte = Serial.read();

    Serial.print((char)incomingByte);
  }
}
```

### PHP script

```php
// Example program for "echo.ino" communication to/from Arduino via USB serial.

use Machinateur\Arduino\streamWrapper;

require_once __DIR__ . '/../../vendor/autoload.php';

// Register the protocol and stream wrapper.
streamWrapper::register();

if ('Darwin' === \PHP_OS_FAMILY) {
    $deviceName = 'tty.usbmodem2101';
    $deviceName = 'cu.usbmodem2101';
} elseif ('Windows' === \PHP_OS_FAMILY) {
    $deviceName = 'COM7';
} else {
    $deviceName = '';

    \trigger_error(
        \sprintf('No device name specified in "%s" on line "%d"!', __FILE__, __LINE__ - 3), \E_USER_ERROR);
}

$deviceBaudRate = 9600;
$deviceParity   =   -1;
$deviceDataSize =    8;
$deviceStopSize =    1;

// Open the connection. Make sure the device is connected under the configured device name
//  and the `echo.ino` program is running.
$fd = \fopen("arduino://{$deviceName}", 'r+b', false);

$input  = 'hello world';
$output = '';

\stream_set_chunk_size($fd, 1);
\fwrite($fd, $input);

do {
    $output .= \fread($fd, 1);

    // Comment in the following line to see the progress byte by byte.
    //echo $output.\PHP_EOL;
} while ($output !== $input);

echo $output,
    \PHP_EOL,
    \PHP_EOL;
```

Open a terminal and run the example, like `php ./echo.php`.

## License

It's MIT.
