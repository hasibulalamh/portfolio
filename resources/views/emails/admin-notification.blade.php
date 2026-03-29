<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>New Contact Message</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; }
  .container { max-width: 600px; margin: 30px auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg, #f093fb, #f5576c); padding: 35px 40px; text-align: center; }
  .header h1 { color: white; font-size: 24px; font-weight: 700; }
  .header p { color: rgba(255,255,255,0.85); margin-top: 8px; }
  .body { padding: 35px 40px; }
  .info-row { display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid #f0f0f0; }
  .info-label { font-weight: 600; color: #4a5568; min-width: 80px; font-size: 14px; }
  .info-value { color: #2d3748; font-size: 14px; }
  .message-box { background: #fff8f0; border-left: 4px solid #f5576c; border-radius: 8px; padding: 18px; margin: 20px 0; }
  .message-box p { color: #4a5568; line-height: 1.7; font-size: 14px; }
  .btn { display: inline-block; background: linear-gradient(135deg, #f093fb, #f5576c); color: white !important; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 600; }
  .footer { background: #2d3748; padding: 20px; text-align: center; }
  .footer p { color: #a0aec0; font-size: 13px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>🔔 New Contact Message!</h1>
    <p>Someone just sent you a message from the portfolio</p>
  </div>
  <div class="body">
    <div class="info-row">
      <span class="info-label">👤 Name</span>
      <span class="info-value">{{ $contactData['name'] }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">📧 Email</span>
      <span class="info-value">{{ $contactData['email'] }}</span>
    </div>
    <div class="info-row">
      <span class="info-label">📌 Subject</span>
      <span class="info-value">{{ $contactData['subject'] ?? 'No subject' }}</span>
    </div>
    <div class="message-box">
      <p><strong>Message:</strong><br><br>{{ $contactData['message'] }}</p>
    </div>
    <div style="text-align:center; margin-top:25px;">
      <a href="{{ config('app.url') }}/halam-panel/contact-messages" class="btn">
        📥 View in Admin Panel
      </a>
    </div>
  </div>
  <div class="footer">
    <p>© {{ date('Y') }} Hasibul Alam Portfolio</p>
  </div>
</div>
</body>
</html>
