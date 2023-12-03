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
