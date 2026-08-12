<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $meta->title }}</title>
    <meta property="og:type" content="event">
    <meta property="og:title" content="{{ $meta->title }}">
    <meta property="og:description" content="{{ $meta->description }}">
    @if ($meta->imageUrl)
        <meta property="og:image" content="{{ $meta->imageUrl }}">
    @endif
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta name="twitter:card" content="{{ $meta->imageUrl ? 'summary_large_image' : 'summary' }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
</head>
<body>
    {{-- Crawlers stop here; this body never renders for a real human (redirected before this view). --}}
    <p>{{ $meta->title }} — <a href="{{ $canonicalUrl }}">{{ $canonicalUrl }}</a></p>
</body>
</html>
