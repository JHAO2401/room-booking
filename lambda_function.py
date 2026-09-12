import json
import os
import smtplib
from email.mime.text import MIMEText

GMAIL_ADDRESS = "yinjh-wm25@student.tarc.edu.my"
GMAIL_APP_PASSWORD = "xznyhktbuyhjlixf"


def build_email(data):
    msg_type = data.get('type')
    room_name = data.get('room_name')
    user_name = data.get('user_name')
    date = data.get('date')
    start_time = data.get('start_time')
    end_time = data.get('end_time')

    if msg_type == 'new_booking_request':
        subject = f"New Room Booking Request - {room_name}"
        body = (
            f"Hi {user_name},\n\n"
            f"Your room booking request has been submitted.\n\n"
            f"Room: {room_name}\n"
            f"Date: {date}\n"
            f"Time: {start_time} - {end_time}\n\n"
            f"You'll get another email once it's approved or rejected.\n\n"
            f"RoomPlate"
        )
    elif msg_type == 'booking_decision':
        decision = data.get('decision', 'updated')
        subject = f"Booking {decision.capitalize()} - {room_name}"
        body = (
            f"Hi {user_name},\n\n"
            f"Your booking has been {decision}.\n\n"
            f"Room: {room_name}\n"
            f"Date: {date}\n"
            f"Time: {start_time} - {end_time}\n\n"
            f"RoomPlate"
        )
    else:
        subject = "RoomPlate Notification"
        body = json.dumps(data, indent=2)

    return subject, body


def send_email(data):
    recipient = data.get('email')
    if not recipient:
        print(f"Skipped: no email address in message: {data}")
        return

    subject, body = build_email(data)

    msg = MIMEText(body)
    msg['Subject'] = subject
    msg['From'] = GMAIL_ADDRESS
    msg['To'] = recipient

    with smtplib.SMTP('smtp.gmail.com', 587) as server:
        server.starttls()
        server.login(GMAIL_ADDRESS, GMAIL_APP_PASSWORD)
        server.sendmail(GMAIL_ADDRESS, [recipient], msg.as_string())

    print(f"Email sent to {recipient} ({data.get('type')})")


def lambda_handler(event, context):
    for record in event.get('Records', []):
        try:
            data = json.loads(record['Sns']['Message'])
            send_email(data)
        except Exception as e:
            print(f"Failed to process record: {e}")

    return {'statusCode': 200}