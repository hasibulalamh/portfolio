<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Message Received</title>
</head>
<body style="margin:0; padding:0; background:#0a0a0a; font-family:'Segoe UI', Arial, sans-serif;">
<div style="padding: 40px 15px;">
<div style="max-width:600px; margin:0 auto; background:#111111; border-radius:4px; overflow:hidden; border:1px solid #222;">

  <!-- Header -->
  <div style="background: linear-gradient(135deg, #92400e 0%, #b45309 50%, #d97706 100%); padding:50px 40px; text-align:center;">
    <div style="font-size:48px; margin-bottom:15px;">✅</div>
    <h1 style="color:#ffffff; font-size:28px; font-weight:300; margin:0 0 10px 0; letter-spacing:2px; text-transform:uppercase;">Message Received</h1>
    <p style="color:rgba(255,255,255,0.8); font-size:15px; margin:0; letter-spacing:1px;">Hi {{ $contactData['name'] }}, I've got your message.</p>
  </div>

  <!-- Thin amber line -->
  <div style="height:1px; background:linear-gradient(to right, transparent, #d97706, transparent);"></div>

  <!-- Body -->
  <div style="padding:45px 40px;">

    <p style="color:#d1d5db; font-size:16px; line-height:1.8; margin:0 0 30px 0;">
      Thank you for reaching out! I've received your message and will get back to you personally within <strong style="color:#d97706;">24-48 hours</strong>.
    </p>

    <!-- Your message box -->
    <div style="border-left:3px solid #d97706; background:#1a1a1a; padding:20px 25px; margin:25px 0; border-radius:0 4px 4px 0;">
      <p style="color:#9ca3af; font-size:12px; text-transform:uppercase; letter-spacing:2px; margin:0 0 10px 0;">Your Message</p>
      <p style="color:#e5e7eb; font-size:14px; line-height:1.7; margin:0; font-style:italic;">
        "{{ \Illuminate\Support\Str::limit($contactData['message'], 200) }}"
      </p>
    </div>

    <!-- What happens next -->
    <p style="color:#9ca3af; font-size:12px; text-transform:uppercase; letter-spacing:2px; margin:35px 0 20px 0;">What Happens Next</p>

    <!-- Timeline -->
    <div style="margin-bottom:15px; display:flex; align-items:flex-start; gap:15px;">
      <div style="width:36px; height:36px; background:#1a1a1a; border:1px solid #d97706; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;">📩</div>
      <div style="padding-top:6px;">
        <strong style="color:#f3f4f6; font-size:14px;">Message Received</strong>
        <p style="color:#6b7280; font-size:13px; margin:3px 0 0 0;">Your message is safely stored and being reviewed</p>
      </div>
    </div>

    <div style="margin-bottom:15px; display:flex; align-items:flex-start; gap:15px;">
      <div style="width:36px; height:36px; background:#1a1a1a; border:1px solid #d97706; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;">🔍</div>
      <div style="padding-top:6px;">
        <strong style="color:#f3f4f6; font-size:14px;">Project Analysis</strong>
        <p style="color:#6b7280; font-size:13px; margin:3px 0 0 0;">I'll review your requirements carefully</p>
      </div>
    </div>

    <div style="margin-bottom:15px; display:flex; align-items:flex-start; gap:15px;">
      <div style="width:36px; height:36px; background:#1a1a1a; border:1px solid #d97706; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;">📋</div>
      <div style="padding-top:6px;">
        <strong style="color:#f3f4f6; font-size:14px;">Proposal & Details</strong>
        <p style="color:#6b7280; font-size:13px; margin:3px 0 0 0;">You'll receive a detailed project proposal via email</p>
      </div>
    </div>

    <div style="margin-bottom:30px; display:flex; align-items:flex-start; gap:15px;">
      <div style="width:36px; height:36px; background:#1a1a1a; border:1px solid #d97706; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;">🚀</div>
      <div style="padding-top:6px;">
        <strong style="color:#f3f4f6; font-size:14px;">Let's Build!</strong>
        <p style="color:#6b7280; font-size:13px; margin:3px 0 0 0;">We'll start working on your amazing project</p>
      </div>
    </div>

    <!-- Response time box -->
    <div style="border:1px solid #292929; background:#1a1a1a; border-radius:4px; padding:20px; text-align:center; margin:30px 0;">
      <p style="color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:2px; margin:0 0 8px 0;">Expected Response Time</p>
      <p style="color:#d97706; font-size:20px; font-weight:600; margin:0;">⏰ Within 24-48 Hours</p>
    </div>

    <!-- CTA Button -->
    <div style="text-align:center; margin-top:35px;">
      <a href="{{ config('app.url') }}"
         style="display:inline-block; background:linear-gradient(135deg, #b45309, #d97706); color:#000000; padding:14px 40px; text-decoration:none; font-weight:600; font-size:14px; letter-spacing:2px; text-transform:uppercase;">
        VIEW MY PORTFOLIO →
      </a>
    </div>

  </div>

  <!-- Divider -->
  <div style="height:1px; background:linear-gradient(to right, transparent, #292929, transparent); margin:0 40px;"></div>

  <!-- Footer -->
  <div style="padding:30px 40px; text-align:center;">
    <p style="color:#f3f4f6; font-size:15px; font-weight:600; margin:0 0 5px 0; letter-spacing:1px;">HASIBUL ALAM</p>
    <p style="color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:2px; margin:0 0 15px 0;">Full Stack Developer</p>
    <p style="margin:0;">
      <a href="mailto:{{ config('mail.from.address') }}"
         style="color:#d97706; font-size:13px; text-decoration:none;">
        {{ config('mail.from.address') }}
      </a>
    </p>
    <p style="color:#374151; font-size:12px; margin:15px 0 0 0;">© {{ date('Y') }} All rights reserved.</p>
  </div>

</div>
</div>
</body>
</html>
