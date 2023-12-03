<?php

declare(strict_types=1);

// Example program for "echo.ino" communication to/from Arduino via USB serial.

use Machinateur\Arduino\streamWrapper;

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
 * ... TODO: Add missing. Also document the Windows options!
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
    'baud=96', 'parity=n', 'data=8', 'stop=1', // Standard options, with some more:
    'to=on', 'xon=off', 'odsr=off', 'octs=off', 'dtr=on', 'rts=on', 'idsr=off',
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

$input  = 'hello world';
$output = '';

// As it turns out, this is the key, as for serial (com) ports on windows represented as file stream:
//  If the stream is read buffered, and it does not represent a plain file, at most one read of up to a number of bytes
//  equal to the chunk size (usually 8192) is made; depending on the previously buffered data, the size of the returned
//  data may be larger than the chunk size.
// This undocumented behaviour of the streamWrapper API may be observed when removing this call and monitoring the value
//  of `$count` in `streamWrapperAbstract::stream_read()` when called during `fread($fd, 1)`. The `$count` will be 8192!
\stream_set_chunk_size($fd, 1);

\fwrite($fd, $input);

do {
    $output .= \fread($fd, 1);
    //echo $output.\PHP_EOL;
} while ($output !== $input);

echo $output,
    \PHP_EOL,
    \PHP_EOL;
