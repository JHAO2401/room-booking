import json
import os
import boto3

ses = boto3.client('ses')


def build_html(data):
    msg_type = data.get('type')

    if msg_type == 'new_booking_request':
        title = "New Room Booking Request"
        color = "#B08D57"
        rows = f"""
            <tr><td><strong>Room</strong></td><td>{data.get('room_name')}</td></tr>
            <tr><td><strong>Requested by</strong></td><td>{data.get('user_name')}</td></tr>
            <tr><td><strong>Date</strong></td><td>{data.get('date')}</td></tr>
            <tr><td><strong>Time</strong></td><td>{data.get('start_time')} – {data.get('end_time')}</td></tr>
        """
        footer = "Log in to the admin dashboard to approve or reject this request."

    elif msg_type == 'booking_decision':
        decision = data.get('decision', 'updated')
        title = f"Booking {decision.upper()}"
        color = "#3F7D58" if decision == 'approved' else "#B4472A"
        rows = f"""
            <tr><td><strong>Room</strong></td><td>{data.get('room_name')}</td></tr>
            <tr><td><strong>Booked by</strong></td><td>{data.get('user_name')}</td></tr>
            <tr><td><strong>Date</strong></td><td>{data.get('date')}</td></tr>
            <tr><td><strong>Time</strong></td><td>{data.get('start_time')} – {data.get('end_time')}</td></tr>
            <tr><td><strong>Status</strong></td><td>{decision.capitalize()}</td></tr>
        """
        footer = ""

    else:
        title = "RoomPlate Notification"
        color = "#21262B"
        rows = f"<tr><td>{json.dumps(data)}</td></tr>"
        footer = ""

    html = f"""
    <html>
      <body style="font-family: Arial, sans-serif; background:#F6F4EF; padding:24px;">
        <div style="max-width:480px; margin:0 auto; background:#fff; border-radius:6px; overflow:hidden; border:1px solid #ddd;">
          <div style="background:{color}; color:#fff; padding:16px 20px; font-size:18px; font-weight:bold;">
            {title}
          </div>
          <div style="padding:20px;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
              {rows}
            </table>
            <p style="color:#666; font-size:13px; margin-top:16px;">{footer}</p>
          </div>
        </div>
      </body>
    </html>
    """
    return title, html


def lambda_handler(event, context):
    sender = os.environ['SES_SENDER_EMAIL']
    recipient = os.environ['SES_RECIPIENT_EMAIL']

    for record in event.get('Records', []):
        sns_message = record['Sns']['Message']

        try:
            data = json.loads(sns_message)
        except (ValueError, TypeError):
            data = {'type': 'raw', 'text': sns_message}

        subject, html_body = build_html(data)

        ses.send_email(
            Source=sender,
            Destination={'ToAddresses': [recipient]},
            Message={
                'Subject': {'Data': subject},
                'Body': {'Html': {'Data': html_body}},
            },
        )

    return {'statusCode': 200, 'body': 'Processed'}