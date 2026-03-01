<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Project Proposal</title>
</head>
<body style="margin:0; padding:0; background:#0a0a0a; font-family:'Segoe UI', Arial, sans-serif;">
<div style="padding: 40px 15px;">
<div style="max-width:600px; margin:0 auto; background:#111111; border-radius:4px; overflow:hidden; border:1px solid #222;">

  <!-- Header -->
  <div style="background: linear-gradient(135deg, #92400e 0%, #b45309 50%, #d97706 100%); padding:50px 40px; text-align:center;">
    <div style="font-size:48px; margin-bottom:15px;">📋</div>
    <h1 style="color:#ffffff; font-size:28px; font-weight:300; margin:0 0 10px 0; letter-spacing:2px; text-transform:uppercase;">Project Proposal</h1>
    <p style="color:rgba(255,255,255,0.8); font-size:15px; margin:0; letter-spacing:1px;">Hi {{ $contact->name }}, here's what I've prepared for you.</p>
  </div>

  <!-- Thin amber line -->
  <div style="height:1px; background:linear-gradient(to right, transparent, #d97706, transparent);"></div>

  <!-- Body -->
  <div style="padding:45px 40px;">

    <p style="color:#d1d5db; font-size:16px; line-height:1.8; margin:0 0 25px 0;">
      Thank you for your patience! I've carefully reviewed your message and prepared a detailed proposal based on your requirements.
    </p>

    <!-- Personal Message Block -->
    <div style="border-left:3px solid #d97706; background:#1a1a1a; padding:20px 25px; margin:25px 0; border-radius:0 4px 4px 0;">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
        <span style="font-size:20px;">👨‍💻</span>
        <div>
          <strong style="color:#d97706; font-size:13px; text-transform:uppercase; letter-spacing:1px;">Personal Message</strong>
        </div>
      </div>
      <p style="color:#e5e7eb; font-size:15px; line-height:1.8; margin:0; white-space:pre-line;">{{ $personalMessage }}</p>
    </div>

    <!-- Divider -->
    <div style="height:1px; background:linear-gradient(to right, transparent, #292929, transparent); margin:30px 0;"></div>

    <!-- Client requirement -->
    <p style="color:#9ca3af; font-size:12px; text-transform:uppercase; letter-spacing:2px; margin:0 0 12px 0;">Your Requirement</p>
    <div style="border-left:3px solid #374151; background:#1a1a1a; padding:18px 25px; margin:0 0 35px 0; border-radius:0 4px 4px 0;">
      <p style="color:#9ca3af; font-size:14px; line-height:1.7; margin:0; font-style:italic;">
        "{{ $contact->message }}"
      </p>
    </div>

    <!-- Recommended Services -->
    <p style="color:#9ca3af; font-size:12px; text-transform:uppercase; letter-spacing:2px; margin:0 0 20px 0;">🎯 Recommended Services</p>

    @foreach($suggestedServices as $service)
    <div style="border:1px solid #292929; border-top:3px solid #d97706; background:#1a1a1a; border-radius:0 0 4px 4px; padding:25px; margin-bottom:20px;">

      <!-- Service Header -->
      <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
        <span style="font-size:28px;">{{ $service['icon'] }}</span>
        <strong style="color:#f3f4f6; font-size:18px; font-weight:600;">{{ $service['name'] }}</strong>
      </div>

      <p style="color:#9ca3af; font-size:14px; line-height:1.6; margin:0 0 18px 0;">{{ $service['description'] }}</p>

      <!-- Features -->
      <p style="color:#6b7280; font-size:11px; text-transform:uppercase; letter-spacing:1px; margin:0 0 10px 0;">What's Included</p>
      <div style="margin-bottom:20px;">
        @foreach($service['features'] as $feature)
        <span style="display:inline-block; background:#111111; border:1px solid #374151; color:#d97706; padding:4px 12px; border-radius:2px; font-size:12px; margin:3px 3px 3px 0; letter-spacing:0.5px;">✓ {{ $feature }}</span>
        @endforeach
      </div>

      <!-- Price & Duration -->
      <div style="display:flex; gap:10px;">
        <div style="flex:1; background:linear-gradient(135deg, #92400e, #d97706); padding:15px; text-align:center; border-radius:2px;">
          <p style="color:rgba(255,255,255,0.7); font-size:10px; text-transform:uppercase; letter-spacing:1px; margin:0 0 5px 0;">Investment</p>
          <p style="color:#ffffff; font-size:15px; font-weight:700; margin:0;">{{ $service['price'] }}</p>
        </div>
        <div style="flex:1; background:#111111; border:1px solid #292929; padding:15px; text-align:center; border-radius:2px;">
          <p style="color:#6b7280; font-size:10px; text-transform:uppercase; letter-spacing:1px; margin:0 0 5px 0;">Timeline</p>
          <p style="color:#f3f4f6; font-size:15px; font-weight:700; margin:0;">{{ $service['duration'] }}</p>
        </div>
      </div>

    </div>
    @endforeach

    <!-- CTA Box -->
    <div style="border:1px solid #292929; background:#1a1a1a; padding:25px; text-align:center; margin:30px 0; border-radius:4px;">
      <p style="color:#9ca3af; font-size:14px; margin:0 0 20px 0;">Interested? Let's discuss your project in detail and get started!</p>
      <a href="{{ config('app.url') }}#contact"
         style="display:inline-block; background:linear-gradient(135deg, #b45309, #d97706); color:#000000; padding:14px 40px; text-decoration:none; font-weight:700; font-size:13px; letter-spacing:2px; text-transform:uppercase;">
        LET'S TALK →
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
