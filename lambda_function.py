import json
import os
import boto3

sns = boto3.client('sns')

NOTIFICATION_TOPIC_ARN = os.environ['SNS_NOTIFICATION_TOPIC_ARN']


def build_message(data):
    msg_type = data.get('type')

    if msg_type == 'new_booking_request':
        subject = "New Room Booking Request"
        body = (
            f"New Room Booking Request\n"
            f"--------------------------\n"
            f"Room: {data.get('room_name')}\n"
            f"Requested by: {data.get('user_name')}\n"
            f"Date: {data.get('date')}\n"
            f"Time: {data.get('start_time')} - {data.get('end_time')}\n\n"
            f"Waiting for approval or rejection of this request."
        )

    elif msg_type == 'booking_decision':
        decision = data.get('decision', 'updated')
        subject = f"Booking {decision.upper()}"
        body = (
            f"Booking {decision.capitalize()}\n"
            f"--------------------------\n"
            f"Room: {data.get('room_name')}\n"
            f"Booked by: {data.get('user_name')}\n"
            f"Date: {data.get('date')}\n"
            f"Time: {data.get('start_time')} - {data.get('end_time')}\n"
            f"Status: {decision.capitalize()}\n"
        )

    else:
        subject = "RoomPlate Notification"
        body = json.dumps(data, indent=2)

    return subject, body


def lambda_handler(event, context):
    for record in event.get('Records', []):
        sns_message = record['Sns']['Message']

        try:
            data = json.loads(sns_message)
        except (ValueError, TypeError):
            data = {'type': 'raw', 'text': sns_message}

        subject, body = build_message(data)

        sns.publish(
            TopicArn=NOTIFICATION_TOPIC_ARN,
            Subject=subject,
            Message=body,
        )

    return {'statusCode': 200, 'body': 'Processed'}