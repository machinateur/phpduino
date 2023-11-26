# phpduino

A user-land stream wrapper implementation for PHP to Arduino communication via USB serial.

## Concept

This package defines an `arduino://` protocol handler using
 a [`streamWrapper`](https://www.php.net/manual/en/class.streamwrapper.php) implementation.
It also provides some byte processing utility functions.

## Requirements

This package requires at least PHP 8.1 to work. No dependencies.

The [Arduino IDE](https://www.arduino.cc/en/software) is still required to upload sketches to the connected device.

## Usage

```php
// First register the protocol and stream wrapper, ...
\Machinateur\Arduino\streamWrapper::register();
// ... then use the protocol in a stream function...
$fd = \fopen('arduino://', 'r+b');
// ... and finally do things with the $fd (fread/fwrite ops).
```

## Installation

```
composer require machinateur/phpduino
```

## Docs

Here are some links to relevant documentation, articles and forum threads:

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
- https://www.php.net/manual/en/class.streamwrapper.php
- https://stackoverflow.com/a/9616217

## Example

You can find an easy example that works with the following code in `./echo` on Mac:

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

// Register the protocol and stream wrapper.
streamWrapper::register();

// The device's name in `/dev/`.
//  Prefer `cu` if on Mac, use `ls /dev/tty.*` to find the available devices.
$deviceName     = 'tty.usbmodem2101';
$deviceName     =  'cu.usbmodem2101';

// The device's baud rate.
//  See Arduino docs at https://docs.arduino.cc/learn/built-in-libraries/software-serial#begin for conventinal rates.
// -> Supported baud rates are 300, 600, 1200, 2400, 4800, 9600, 14400, 19200, 28800, 31250, 38400, 57600, and 115200 bauds.
$deviceBaudRate = 9600;

// The device's parity bit configuration.
//  See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
// -> none = -1
// ->  odd =  1
// -> even =  0
$deviceParity   = -1;

// The device's data size configuration.
//  See Arduino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
// -> Default is `SERIAL_8N1`, so 8 is the data size.
$deviceDataSize = 8;

// The device's stop bit size configuration.
//  See Adruino docs at https://www.arduino.cc/reference/en/language/functions/communication/serial/begin/.
// -> Default is `SERIAL_8N1`, so 1 is the stop bit size.
$deviceStopSize = 1;

// The stream context configuration.
$context = \stream_context_create([
    streamWrapper::PROTOCOL_NAME => [
        'baud_rate'      => $deviceBaudRate,
        'parity'         => $deviceParity,
        'data_size'      => $deviceDataSize,
        'stop_size'      => $deviceStopSize,
        'custom_command' => 'Darwin' === \PHP_OS_FAMILY
            ? 'ignbrk -brkint -icrnl -imaxbel -opost -onlcr -isig -icanon -iexten -echo -echoe -echok -echoctl -echoke noflsh'
            : null,
    ],
]);

// Open the connection. Make sure the device is connected under the configured device name
//  and the `echo.ino` program is running.
$fd = \fopen("arduino://{$deviceName}", 'r+b', false, $context);

\stream_set_blocking(\STDIN, true);
echo 'echo: type and get a response...',
    \PHP_EOL,
    \PHP_EOL;

$waiting = false;
$input   = false;
$output  = '';

\fread($fd, 1);

while (!\feof($fd))
{
    if (!$waiting) {
        $input = \fgets(\STDIN);

        if ($input) {
            $waiting = false !== \fwrite($fd, $input);
        }
    }

    \usleep(5_000);

    $output .= \fread($fd, 1);

    if (!$output) {
        echo '.';

        continue;
    }

    if ($output === $input) {
        echo $output;

        $waiting = false;
        $input   = false;
        $output  = '';
    }
}

echo \PHP_EOL,
    \PHP_EOL;
```

Open a terminal and run the example, like `php ./echo.php`.

## License

It's MIT.
