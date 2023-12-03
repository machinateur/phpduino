<?php

declare(strict_types=1);

use Machinateur\Arduino\streamWrapper;
use Machinateur\Arduino;

require_once __DIR__ . '/../../vendor/autoload.php';

// Register the protocol and stream wrapper.
streamWrapper::register();

// The device's name in `/dev/`.
//  Prefer `cu` if on Mac, use `ls /dev/tty.*` to find the available devices.
$deviceName     = 'tty.usbmodem2101';
$deviceName     =  'cu.usbmodem2101';
//$deviceName     = 'COM7';

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

/**
 * These are the option's required on Mac for the communication to succeed.
 * ... TODO: Add missing.
 * - `ignbrk`   = ignore break characters
 * - `-brkint`  = breaks [don't] cause an interrupt signal
 * - `-icrnl`   = [don't] translate carriage return to newline
 * - `-imaxbel` = [don't] beep and [...] flush a full input buffer on a character
 * - `-opost`   = [don't] postprocess output
 * - `-onlcr`   = [don't] translate newline to carriage return-newline
 * - `-isig`    = [don't] enable interrupt, quit, and suspend special characters
 * - `-icanon`  = [don't] enable special characters: erase, kill, werase, rprnt
 * - `-iexten`  = [don't] enable non-POSIX special characters
 * - `-echo`    = [don't] echo input characters
 * - `-echoe`   = [don't] echo erase characters as backspace-space-backspace
 * - `-echok`   = [don't] echo a newline after a kill character
 * - `-echoctl` = [don't] echo control characters in hat notation ('^c')
 * - `-echoke`  = kill all line by obeying the echoctl and echok settings
 * - `noflsh`   = disable flushing after interrupt and quit special characters
 */
$deviceCustomCommand = 'Darwin' === \PHP_OS_FAMILY ? [
    '9600', '-parenb', '-cstopb', 'clocal', '-crtscts', '-ixon', '-ixoff',
    '-hup', // Try to stop the reset on fclose(), see https://stackoverflow.com/a/59549518.
    // Ideally only run with the `-hup` option if not yet set, so there will be no more restarts due to RTS HANGUP.
    'ignbrk', '-brkint', '-icrnl', '-imaxbel', '-opost', '-onlcr', '-isig', '-icanon', '-iexten', '-echo', '-echoe',
    '-echok', '-echoctl', '-echoke', 'noflsh',
] : [
    // TODO: Add windows command. Move to concrete implementation when finalized.
];

// The stream context configuration.
$context = \stream_context_create([
    streamWrapper::PROTOCOL_NAME => [
        'baud_rate'      => $deviceBaudRate,
        'parity'         => $deviceParity,
        'data_size'      => $deviceDataSize,
        'stop_size'      => $deviceStopSize,
        'custom_command' => $deviceCustomCommand,
        'usleep_s'       => 2, // A safe threshold for the arduino to boot on fopen().
    ],
]);

// Open the connection. Make sure the device is connected under the configured device name
//  and the `echo.ino` program is running.
$fd = \fopen("arduino://{$deviceName}", 'r+b', false, $context);

/** @var array<int> $struct */
$struct = \array_map(\ord(...), \str_split($input = 'binary transmission of an ascii string message'));

echo \fwrite($fd, $input = Arduino\byte_pack($struct), \count($struct)),
    \PHP_EOL;

echo 'Input:        ',
    \print_r(\array_map(\dechex(...), $struct), true);
echo 'Input:        ',
    \print_r($input, true),
    \PHP_EOL;
echo 'Input HEX:    ',
    \print_r(\bin2hex(Arduino\byte_pack($struct)), true),
    \PHP_EOL;

$output = '';
while (!\feof($fd)) {
    \usleep(5_000);

    $output .= \fread($fd, 1);

    if (!$output) {
        echo '.';

        continue;
    }

    echo $output,
        \PHP_EOL;

    if ($output === $input) {
        break;
    }
}

\fclose($fd);

echo 'Output:       ',
    \print_r($output, true),
    \PHP_EOL;
echo 'Output HEX:   ',
    \print_r(\array_map(\dechex(...), Arduino\byte_unpack($output)), true),
    \PHP_EOL;
