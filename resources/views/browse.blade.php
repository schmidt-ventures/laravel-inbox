<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $current ? $current->subjectLine().' · Local inbox' : 'Local inbox' }}</title>
    <style>
        :root {
            color-scheme: light;
            --paper: #f3efe7;
            --panel: #fffcf7;
            --ink: #1d1a16;
            --muted: #6d645b;
            --line: #e4dacb;
            --accent: #0e6b66;
            --accent-soft: #e5f3f1;
            --danger: #8d3b32;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--paper);
            color: var(--ink);
            font: 15px/1.5 ui-sans-serif, system-ui, sans-serif;
        }

        header, .shell, a, button { font: inherit; }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            border-bottom: 1px solid var(--line);
            background: var(--panel);
        }

        .eyebrow {
            margin: 0;
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 { margin: 0; font-size: 22px; font-weight: 650; }

        .header-actions, .tabs, .attachments { display: flex; gap: 8px; align-items: center; }

        button, .button {
            border: 1px solid var(--line);
            background: var(--panel);
            color: var(--ink);
            border-radius: 999px;
            padding: 6px 12px;
            text-decoration: none;
            cursor: pointer;
        }

        button:hover, .button:hover { border-color: var(--accent); }

        button:focus-visible, a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

        button[disabled] { opacity: 0.45; cursor: default; }

        .danger { color: var(--danger); }

        .shell {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            min-height: calc(100vh - 77px);
        }

        nav {
            border-right: 1px solid var(--line);
            background: #f7f3ec;
            overflow: auto;
        }

        .message-link {
            display: block;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            color: inherit;
            text-decoration: none;
        }

        .message-link.is-selected { background: var(--accent-soft); box-shadow: inset 3px 0 var(--accent); }

        .subject, .meta, time { display: block; }

        .subject { font-weight: 650; overflow-wrap: anywhere; }

        .meta, time, .placeholder p, .field span { color: var(--muted); }

        time { font-size: 12px; }

        main { min-width: 0; padding: 24px; }

        .placeholder, .letter {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            min-height: 420px;
        }

        .placeholder { display: grid; place-items: center; text-align: center; padding: 32px; }

        .placeholder h2 { margin: 0 0 8px; }

        .letter { padding: 20px; }

        .fields { display: grid; gap: 4px; margin: 0 0 16px; }

        .field { display: grid; grid-template-columns: 72px minmax(0, 1fr); gap: 8px; }

        .field strong { overflow-wrap: anywhere; font-weight: 550; }

        .tabs { margin-bottom: 12px; }

        .tabs a { border-radius: 999px; padding: 4px 10px; text-decoration: none; color: var(--muted); }

        .tabs a.is-selected { background: var(--ink); color: white; }

        iframe { width: 100%; min-height: 520px; border: 1px solid var(--line); border-radius: 12px; background: white; }

        pre {
            margin: 0;
            padding: 16px;
            overflow: auto;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            background: #1d1a16;
            color: #f6f1e8;
            border-radius: 12px;
        }

        .attachments { flex-wrap: wrap; margin-top: 16px; }

        .empty { margin: 0; padding: 20px 16px; color: var(--muted); }

        @media (max-width: 800px) {
            .shell { grid-template-columns: 1fr; }
            nav { max-height: 36vh; border-right: 0; border-bottom: 1px solid var(--line); }
            .field { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <p class="eyebrow">Local only · not delivered</p>
            <h1>Inbox</h1>
        </div>
        <div class="header-actions">
            <a class="button" href="{{ $current ? route('inbox.show', ['message' => $current->id, 'tab' => $tab]) : route('inbox.index') }}">Refresh</a>
            <form method="POST" action="{{ route('inbox.destroy-all') }}" onsubmit="return confirm('Clear every captured message?')">
                <input type="hidden" name="_method" value="DELETE">
                <input type="hidden" name="inbox_token" value="{{ $token }}">
                <button type="submit" @disabled($messages === [])>Clear</button>
            </form>
        </div>
    </header>
    <div class="shell">
        <nav aria-label="Captured messages">
            @forelse ($messages as $item)
                <a class="message-link {{ $current && $current->id === $item->id ? 'is-selected' : '' }}" href="{{ route('inbox.show', $item->id) }}" @if ($current && $current->id === $item->id) aria-current="page" @endif>
                    <span class="subject">{{ $item->subjectLine() }}</span>
                    <span class="meta">To {{ $item->recipientLine() }}</span>
                    <time datetime="{{ $item->createdAt }}">{{ $item->sentAtLabel() }}</time>
                </a>
            @empty
                <p class="empty">No messages yet. Mail sent with the inbox mailer will show up here.</p>
            @endforelse
        </nav>
        <main>
            @if ($current)
                <article class="letter">
                    <h2>{{ $current->subjectLine() }}</h2>
                    <dl class="fields">
                        <div class="field"><span>From</span><strong>{{ $current->addressLine($current->from) ?: 'Unknown' }}</strong></div>
                        <div class="field"><span>To</span><strong>{{ $current->recipientLine() }}</strong></div>
                        @if ($current->cc !== [])
                            <div class="field"><span>Cc</span><strong>{{ $current->addressLine($current->cc) }}</strong></div>
                        @endif
                        @if ($current->bcc !== [])
                            <div class="field"><span>Bcc</span><strong>{{ $current->addressLine($current->bcc) }}</strong></div>
                        @endif
                        <div class="field"><span>Sent</span><strong>{{ $current->sentAtLabel() }}</strong></div>
                    </dl>
                    <div class="tabs" role="tablist">
                        @foreach (['html' => 'HTML', 'text' => 'Text', 'source' => 'Source'] as $name => $label)
                            <a role="tab" href="{{ route('inbox.show', ['message' => $current->id, 'tab' => $name]) }}" class="{{ $tab === $name ? 'is-selected' : '' }}" @if ($tab === $name) aria-current="page" @endif>{{ $label }}</a>
                        @endforeach
                    </div>
                    @if ($tab === 'html')
                        <iframe sandbox referrerpolicy="no-referrer" title="HTML message" src="{{ route('inbox.html', $current->id) }}"></iframe>
                    @elseif ($tab === 'text')
                        <pre>{{ $current->text ?? 'This message has no text part.' }}</pre>
                    @else
                        <pre>{{ $current->raw ?? 'The raw message is unavailable.' }}</pre>
                    @endif
                    @if ($current->attachments !== [])
                        <div class="attachments">
                            @foreach ($current->attachments as $index => $attachment)
                                <a class="button" href="{{ route('inbox.attachment', ['message' => $current->id, 'index' => $index]) }}">{{ $current->attachmentLabel($attachment) }}</a>
                            @endforeach
                        </div>
                    @endif
                    <form method="POST" action="{{ route('inbox.destroy', $current->id) }}" style="margin-top: 16px">
                        <input type="hidden" name="_method" value="DELETE">
                        <input type="hidden" name="inbox_token" value="{{ $token }}">
                        <button class="danger" type="submit">Delete</button>
                    </form>
                    @if ($current->headers !== [])
                        <details style="margin-top: 16px">
                            <summary>Headers</summary>
                            <pre>{{ implode("\n", $current->headers) }}</pre>
                        </details>
                    @endif
                </article>
            @else
                <div class="placeholder">
                    <div>
                        <h2>Nothing selected</h2>
                        <p>Captured mail stays on this machine. It is not sent to a mail server.</p>
                    </div>
                </div>
            @endif
        </main>
    </div>
</body>
</html>
