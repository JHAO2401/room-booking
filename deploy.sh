#!/bin/bash
zip -r room-booking.zip . -x "*.git*"
aws s3 cp room-booking.zip s3://room-booking-bucket/room-booking.zip
aws ssm send-command \
  --instance-ids "i-041d92fe3c71910d7" \
  --document-name "AWS-RunShellScript" \
  --parameters commands='["aws s3 cp s3://room-booking-bucket/room-booking.zip /tmp/room-booking.zip","cd /tmp","rm -rf room-booking","unzip -o room-booking.zip -d room-booking","sudo cp -r room-booking/* /var/www/html/","sudo chown -R apache:apache /var/www/html","sudo systemctl restart httpd"]'