<?php
// Link-preview cards (1200×630 PNG) for players, clans and world stats; every other page shares the banner.
const CARD_FONTS = '/usr/share/fonts/truetype/dejavu/';
const CARD_W = 1200, CARD_H = 630;

// The page's preview: [description, image path].
function card_meta(array $s, $d): array {
    if ($s['view'] === 'player' && $d !== null) {
        $o = array_column($d['stats'], null, 'type')[0] ?? null;
        return [implode(' · ', array_filter([$o ? 'Total level ' . fmt($o['level']) : null, $o ? xp($o['value']) . ' XP' : null, ehp($d['ehp'][0]) . ' EHP', $o ? 'Rank ' . fmt($o['rank']) : null])), '/og/player/' . $s['name']];
    }
    if ($s['view'] === 'clans' && $s['clan'] !== null) return [fmt(count($d['members'])) . ' members · Total level ' . fmt(clan_total_level($d['levels'], 'total')) . ' · ' . ehp(array_sum(array_map(fn($m) => end($m['series'])['ehp'], $d['members']))) . ' EHP', '/og/clans/' . $s['clan']];
    if ($s['view'] === 'stats') return ['+' . fmt($d['today']) . ' XP today · +' . fmt($d['week']) . ' XP this week · ' . fmt($d['players']) . ' tracked players', '/og/stats'];
    return ['XP tracker for Lost City: hiscores, XP records, clans and EHP.', '/og'];
}

function card_image(PDO $db, string $path, int $now): GdImage {
    if (!preg_match('~^/og(?:/(player|clans)/([a-z0-9_]{1,12})|/(stats))?$~', $path, $m)) throw new OutOfBoundsException('Page not found');
    if (($m[1] ?? '') === 'player') {
        $d = get_data($db, ['q' => 'player', 'name' => $m[2], 'period' => '7d'], $now) ?? throw new OutOfBoundsException('Player not tracked');
        return player_card($m[2], $d, clan_map($db)[$m[2]] ?? []);
    }
    if (($m[1] ?? '') === 'clans') {
        if (!isset(CLANS[$m[2]])) throw new OutOfBoundsException('Page not found');
        $d = get_data($db, ['q' => 'clan', 'clan' => $m[2], 'period' => '7d'], $now);
        return stat_card(clan_icon_image($m[2], 110), CLANS[$m[2]]['name'], 'Clan on Lost City', [['Members', fmt(count($d['members']))], ['Total level', fmt(clan_total_level($d['levels'], 'total'))], ['Total EHP', ehp(array_sum(array_map(fn($x) => end($x['series'])['ehp'], $d['members']))), 'accent']]);
    }
    if (($m[3] ?? '') === 'stats') {
        $d = get_data($db, ['q' => 'stats'], $now);
        return stat_card(null, 'World stats', fmt($d['players']) . ' tracked players', [['XP gained today', '+' . fmt($d['today']), 'gain'], ['Last 7 days', '+' . fmt($d['week']), 'gain'], ['99s', fmt(array_sum($d['nineties']))]]);
    }
    return banner_card();
}

function card_font(string $file): string {
    return is_file(CARD_FONTS . $file) ? CARD_FONTS . $file : throw new RuntimeException('Missing ' . CARD_FONTS . $file . '; install fonts-dejavu-core');
}
function card_color(GdImage $im, string $name): int {
    $rgb = ['head' => 0xeaded4, 'accent' => 0xdfa18b, 'muted' => 0xb4a69a, 'gain' => 0xe0cf9c, 'border' => 0x514039, 'shadow' => 0x000000][$name];
    return imagecolorallocate($im, $rgb >> 16, $rgb >> 8 & 255, $rgb & 255);
}
// $px is the cap-to-descender pixel size; GD sizes are points at 96 DPI and y is the baseline.
function card_text(GdImage $im, int $px, int $x, int $top, string $color, string $text, bool $serif = true, bool $shadow = false): int {
    $font = card_font($serif ? 'DejaVuSerif.ttf' : 'DejaVuSans.ttf'); $pt = $px * .75; $base = $top + (int) round($px * .8);
    if ($shadow) imagettftext($im, $pt, 0, $x + 3, $base + 3, card_color($im, 'shadow'), $font, $text);
    $box = imagettftext($im, $pt, 0, $x, $base, card_color($im, $color), $font, $text);
    return $box[2];
}
function card_width(int $px, string $text, bool $serif = true): int {
    $box = imagettfbbox($px * .75, 0, card_font($serif ? 'DejaVuSerif.ttf' : 'DejaVuSans.ttf'), $text);
    return $box[2] - $box[0];
}
function card_png(string $file): GdImage {
    $im = imagecreatefrompng(__DIR__ . '/../public/assets/' . $file) ?: throw new RuntimeException("Cannot read asset $file");
    imagepalettetotruecolor($im); imagealphablending($im, true);
    return $im;
}
function card_paste(GdImage $im, GdImage $src, int $x, int $y, int $w, int $h, ?array $crop = null): void {
    [$sx, $sy, $sw, $sh] = $crop ?? [0, 0, imagesx($src), imagesy($src)];
    // Pixel art is enlarged without smoothing; large artwork is shrunk smoothly.
    if ($w >= $sw) imagecopyresized($im, $src, $x, $y, $sx, $sy, $w, $h, $sw, $sh);
    else imagecopyresampled($im, $src, $x, $y, $sx, $sy, $w, $h, $sw, $sh);
}
function skill_icon_image(GdImage $im, int $type, int $x, int $y, int $size): void {
    static $sprite; $sprite ??= card_png('skills.png');
    card_paste($im, $sprite, $x, $y, $size, $size, [array_search($type, array_keys(SKILLS), true) * 25, 0, 25, 25]);
}
function clan_icon_image(string $key, int $h): GdImage {
    $src = card_png(substr(CLANS[$key]['icon'], strlen('/assets/')));
    $w = (int) round(imagesx($src) * $h / imagesy($src));
    $out = imagecreatetruecolor($w, $h); imagealphablending($out, false); imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127)); imagesavealpha($out, true);
    card_paste($out, $src, 0, 0, $w, $h);
    return $out;
}
function card_base(): GdImage {
    $im = imagecreatetruecolor(CARD_W, CARD_H);
    $bg = imagecreatefromjpeg(__DIR__ . '/../public/assets/background.jpg') ?: throw new RuntimeException('Cannot read background.jpg');
    for ($x = 0; $x < CARD_W; $x += imagesx($bg)) for ($y = 0; $y < CARD_H; $y += imagesy($bg)) imagecopy($im, $bg, $x, $y, 0, 0, imagesx($bg), imagesy($bg));
    imagefilledrectangle($im, 0, 0, CARD_W, CARD_H, imagecolorallocatealpha($im, 12, 6, 4, 82));
    imagefilledrectangle($im, 40, 40, CARD_W - 40, CARD_H - 40, imagecolorallocate($im, 13, 9, 8));
    foreach ([[110, 90, 80], [60, 46, 40], [110, 90, 80], [40, 30, 26]] as $i => [$r, $g, $b]) imagerectangle($im, 40 + $i, 40 + $i, CARD_W - 40 - $i, CARD_H - 40 - $i, imagecolorallocate($im, $r, $g, $b));
    return $im;
}
function card_rule(GdImage $im, int $y): void {imagefilledrectangle($im, 70, $y, CARD_W - 70, $y + 1, card_color($im, 'border'));}
function card_footer(GdImage $im): void {
    card_rule($im, 485);
    card_paste($im, card_png('stats.png'), 70, 504, 50, 48);
    $x = card_text($im, 30, 132, 512, 'head', 'Lost City ');
    card_text($im, 30, $x, 512, 'accent', 'Labs');
    card_text($im, 22, CARD_W - 70 - card_width(22, 'labs.lostcity.rs', false), 518, 'muted', 'labs.lostcity.rs', false);
}
// $stats: [[label, value, ?colour], ...] across one row.
function card_stats(GdImage $im, int $y, array $stats): void {
    $col = (CARD_W - 140) / count($stats);
    foreach ($stats as $i => $stat) {
        $x = (int) (70 + $i * $col);
        if ($i) imagefilledrectangle($im, $x - 20, $y, $x - 19, $y + 92, card_color($im, 'border'));
        card_text($im, 24, $x, $y, 'muted', $stat[0], false);
        for ($px = 50; card_width($px, $stat[1]) > $col - 40; $px -= 2);
        card_text($im, $px, $x, $y + 36 + (int) ((50 - $px) * .7), $stat[2] ?? 'head', $stat[1]);
    }
}
function stat_card(?GdImage $icon, string $title, string $subtitle, array $stats): GdImage {
    $im = card_base(); $x = 70;
    if ($icon) {imagecopy($im, $icon, 70, 70, 0, 0, imagesx($icon), imagesy($icon)); $x += imagesx($icon) + 30;}
    card_text($im, 84, $x, 85, 'head', $title, true, true);
    card_text($im, 26, $x + 2, 195, 'muted', $subtitle, false);
    card_rule($im, 255); card_stats($im, 285, $stats); card_footer($im);
    return $im;
}
function player_card(string $name, array $d, array $clans): GdImage {
    $im = card_base(); $o = array_column($d['stats'], null, 'type')[0] ?? null;
    $skills = array_diff_key($d['ehp'], [0 => true]); arsort($skills); $top = array_slice(array_keys(array_filter($skills, fn($v) => $v > 0)), 0, 3);
    if ($top) skill_icon_image($im, $top[0], 70, 78, 100);
    $x = card_text($im, 84, 190, 85, 'head', ucwords(str_replace('_', ' ', $name)), true, true) + 22;
    foreach ($clans as $key) {$icon = clan_icon_image($key, 70); imagecopy($im, $icon, $x, 100, 0, 0, imagesx($icon), imagesy($icon)); $x += imagesx($icon) + 14;}
    if ($top) card_text($im, 26, 192, 195, 'muted', 'Most EHP in ' . implode(', ', array_map(fn($t) => SKILLS[$t], $top)), false);
    card_rule($im, 255);
    card_stats($im, 285, [['Total level', $o ? fmt($o['level']) : '–'], ['Total XP', $o ? xp($o['value']) : '–'], ['EHP', ehp($d['ehp'][0])], ['Overall rank', $o ? fmt($o['rank']) : '–', 'accent']]);
    card_rule($im, 405);
    card_text($im, 36, 70, 425, 'gain', $o && $o['gain'] > 0 ? '+' . xp($o['gain']) . ' XP this week' : 'No XP gained this week');
    card_footer($im);
    return $im;
}
function banner_card(): GdImage {
    $im = card_base();
    card_paste($im, card_png('stats.png'), 500, 105, 200, 192);
    $w = card_width(84, 'Lost City Labs'); $x = (int) ((CARD_W - $w) / 2);
    $x = card_text($im, 84, $x, 320, 'head', 'Lost City ', true, true);
    card_text($im, 84, $x, 320, 'accent', 'Labs', true, true);
    $tag = 'XP tracker for Lost City';
    card_text($im, 34, (int) ((CARD_W - card_width(34, $tag, false)) / 2), 445, 'muted', $tag, false);
    $types = array_keys(array_diff_key(SKILLS, [0 => true]));
    foreach ($types as $i => $type) skill_icon_image($im, $type, (int) ((CARD_W - count($types) * 46) / 2 + $i * 46), 505, 36);
    return $im;
}
