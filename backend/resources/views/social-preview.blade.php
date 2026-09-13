<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
     <meta charset="UTF-8" />
     <meta name="viewport" content="width=device-width, initial-scale=1.0" />
     <title>{{ $title }}</title>

     <!-- SEO Meta -->
     <meta name="description" content="{{ $description }}" />
     <meta name="robots" content="noindex, nofollow" />

     <!-- Open Graph / Facebook / WhatsApp -->
     <meta property="og:type" content="website" />
     <meta property="og:url" content="{{ $url }}" />
     <meta property="og:title" content="{{ $title }}" />
     <meta property="og:description" content="{{ $description }}" />
     <meta property="og:image" content="https://digitals-labs.com/og-image.png" />
     <meta property="og:image:width" content="1200" />
     <meta property="og:image:height" content="630" />
     <meta property="og:image:alt" content="{{ $labName }} - نتائج المختبر" />
     <meta property="og:locale" content="ar_AR" />
     <meta property="og:locale:alternate" content="en_US" />
     <meta property="og:locale:alternate" content="ku_IQ" />
     <meta property="og:site_name" content="{{ $labName }}" />

     <!-- Twitter Card -->
     <meta name="twitter:card" content="summary_large_image" />
     <meta name="twitter:title" content="{{ $title }}" />
     <meta name="twitter:description" content="{{ $description }}" />
     <meta name="twitter:image" content="https://digitals-labs.com/og-image.png" />
     <meta name="twitter:image:alt" content="{{ $labName }} - نتائج المختبر" />

     <!-- Redirect non-bot browsers to SPA -->
     <meta http-equiv="refresh" content="0;url={{ $url }}" />
</head>
<body>
     <h1>{{ $title }}</h1>
     <p>{{ $description }}</p>
     <p><a href="{{ $url }}">{{ $url }}</a></p>
</body>
</html>
