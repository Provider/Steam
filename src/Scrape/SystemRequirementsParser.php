<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\StaticClass;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses the system requirements from a Steam store page for every supported platform into a
 * consistent, canonical format.
 *
 * Steam publishes separate Windows (`data-os="win"`), macOS (`data-os="mac"`) and Linux
 * (`data-os="linux"`) blocks. Each platform exposes a minimum tier and an optional recommended
 * tier, even when only one block is present. Every tier exposes the same four groups — OS,
 * processor, memory and graphics — regardless of how the publisher labelled or ordered them, so
 * every game can be matched against with the same code.
 *
 * Requirements are keyed by platform: `windows`, `mac` and `linux`.
 *
 * Free text is inherently variable ("1.2GHz processor", "Quad-core Intel or AMD processor",
 * "DirectX 8-compatible graphics card with at least 32MB of video memory"), so each group carries
 * both the raw string and best-effort canonical values:
 *
 * - Memory: `mb` is the stated size in mebibytes.
 * - Processor: `speed_ghz` is the slowest clock speed mentioned (alternatives are read as "any of",
 *   so the slowest is the effective floor), `cores`/`threads` are the smallest core/thread counts
 *   mentioned, and `models` lists the named CPU families/models with qualifiers ("or higher"),
 *   speeds and generic words stripped, plus common shorthands expanded ("P4" → "Pentium 4"). An
 *   empty list means the requirement is generic (any CPU at the stated speed and core count
 *   suffices) — the "any processor" case.
 * - Graphics: `vram_mb` is the smallest video memory size mentioned (including a "VRAM …" note in
 *   the additional notes, when present), and `models` lists the named GPU families/models. An empty
 *   list means the requirement is generic (any GPU with the stated VRAM suffices) — the "any
 *   graphics card" case.
 * - OS: `windows` lists every mentioned Windows version, oldest first, `minimum_windows` is the
 *   oldest (a newer Windows satisfies an older requirement), and `requires_64bit` detects a 64-bit
 *   requirement in the OS string or notes (a combined 32/64-bit list does not require 64-bit).
 *   macOS and Linux requirements keep their raw OS text; `windows` is empty for those platforms.
 *
 * Model matching should be case-insensitive. A future CPU/GPU ranking table can map `models`
 * entries to performance scores; the scraper's job is only to extract them consistently.
 *
 * Each tier has the shape `[os: [raw, windows, minimum_windows, requires_64bit], processor:
 * [raw, speed_ghz, cores, threads, models], memory: [raw, mb], graphics: [raw, vram_mb, models]]`.
 *
 * @return ?array{platforms: array<string, array{minimum: ?array, recommended: ?array}>}
 *     Null when the page has no usable requirements.
 */
final class SystemRequirementsParser
{
    use StaticClass;

    /** Steam data-os attribute for each requirement platform, in display order. */
    private const PLATFORMS = [
        'windows' => 'win',
        'mac' => 'mac',
        'linux' => 'linux',
    ];

    /** Windows versions ordered oldest first, for canonical sorting and minimum detection. */
    private const WINDOWS_RANK = [
        '98' => 0,
        'ME' => 1,
        '2000' => 2,
        'XP' => 3,
        'Vista' => 4,
        '7' => 5,
        '8' => 6,
        '8.1' => 7,
        '10' => 8,
        '11' => 9,
    ];

    /** Fragment classifiers for legacy flat requirements text. */
    private const OS_FRAGMENT_PATTERN = '/windows|\bXP\b|\bVista\b|\bmac\s*os\b|\bos\s*x\b|\bmacos\b'
        . '|\blinux\b|\bubuntu\b|\bsteamos\b|\bsteam\s*os\b|\bfedora\b|\bdebian\b|\bmint\b/i';
    private const PROCESSOR_FRAGMENT_PATTERN = '/\bmhz\b|\bghz\b|processor|\bcpu\b|\bcore\b|threads?'
        . '|apple\s+m\d/i';
    private const MEMORY_FRAGMENT_PATTERN = '/\bram\b|memory/i';
    private const GRAPHICS_FRAGMENT_PATTERN = '/video\s*cards?|video\s*memory|graphics\s*memory|graphics'
        . '|\bvram\b|pixel\s*shader|directx/i';
    private const GRAPHICS_MODEL_PATTERN = '/\b(gtx|rtx|rx|r9|r7|hd|gt)\s*\d/i';

    /** Qualifiers stripped from model names ("or higher", "or equivalent", …). */
    private const QUALIFIERS = 'higher|better|faster|equivalent|similar|greater';

    /** Vendor and topology words stripped to test whether a processor name is generic. */
    private const GENERIC_PROCESSOR_WORDS_PATTERN = '/\b(dual|quad|tri|single|multi|hexa|octa|core|cores'
        . '|intel|amd|arm|processor|cpu|apu)\b/i';

    /** Common model shorthands expanded to canonical names ("P4" → "Pentium 4"). */
    private const MODEL_ALIASES = [
        'p4' => 'Pentium 4',
        'amd64x2' => 'Athlon 64 X2',
    ];

    /** Known GPU families, for telling model names apart from generic descriptions. */
    private const GPU_FAMILY_PATTERN = '/geforce|radeon|\bati\b|\barc\b|iris|\buhd\b|vega|\brdna\b'
        . '|powervr|\bmali\b|\badreno\b|turing|ampere|lovelace|apple/i';

    public static function parseRequirements(Crawler $crawler): ?array
    {
        $platforms = [];

        foreach (self::PLATFORMS as $platform => $dataOs) {
            $tiers = self::parsePlatform($crawler, $dataOs, $platform);

            if ($tiers !== null) {
                $platforms[$platform] = $tiers;
            }
        }

        $platforms = array_filter(
            $platforms,
            static fn (array $tiers): bool => $tiers['minimum'] !== null || $tiers['recommended'] !== null,
        );

        if ($platforms === []) {
            return null;
        }

        return ['platforms' => $platforms];
    }

    /**
     * @return ?array{minimum: ?array, recommended: ?array} Null when the block is absent or empty.
     */
    private static function parsePlatform(Crawler $crawler, string $dataOs, string $platform): ?array
    {
        $blocks = $crawler->filter(".game_area_sys_req[data-os=\"$dataOs\"]");

        if (!$blocks->count()) {
            return null;
        }

        $block = new NativeCrawler($blocks->eq(0)->getNode(0));
        $left = $block->filter('.game_area_sys_req_leftCol');
        $right = $block->filter('.game_area_sys_req_rightCol');
        $full = $block->filter('.game_area_sys_req_full');

        // Two-column layout: left is minimum, right is recommended. The tier is positional: legacy
        // pages label both columns "Minimum:", so the header text cannot be trusted.
        if ($left->count()) {
            $minimum = self::parseColumn($left, $platform);
            $recommended = $right->count() ? self::parseColumn($right, $platform) : null;

            return $minimum === null && $recommended === null
                ? null
                : ['minimum' => $minimum, 'recommended' => $recommended];
        }

        if ($full->count()) {
            // Legacy single-column layout with both tiers as flat text (e.g. Counter-Strike).
            [$minimumText, $recommendedText] = self::splitLegacyTiers($full);

            if ($minimumText !== null && $recommendedText !== null) {
                $minimum = self::parseFlatTier($minimumText, $platform);
                $recommended = self::parseFlatTier($recommendedText, $platform);

                return $minimum === null && $recommended === null
                    ? null
                    : ['minimum' => $minimum, 'recommended' => $recommended];
            }

            if (($minimum = self::parseColumn($full, $platform)) === null) {
                return null;
            }

            return ['minimum' => $minimum, 'recommended' => null];
        }

        return null;
    }

    /**
     * Splits a single-column block containing both "Minimum:" and "Recommended:" flat text sections.
     *
     * @return array{0: ?string, 1: ?string} [minimum, recommended], both null when not applicable.
     */
    private static function splitLegacyTiers(Crawler $column): array
    {
        if ($column->filter('li')->count() > 0) {
            return [null, null];
        }

        $text = self::normalizeSpace($column->text());

        if (preg_match('/^Minimum:(.*?)Recommended:(.*)$/isu', $text, $matches)) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [null, null];
    }

    /**
     * Parses one requirements column in any of the markups publishers use:
     * - Labelled list (`<li><strong>Label:</strong> …`),
     * - `<strong>Label:</strong>` entries separated by `<br>` (no list items),
     * - Flat text with a single tier header.
     *
     * Returns null when the column contains no usable requirements data.
     */
    private static function parseColumn(Crawler $column, string $platform = 'windows'): ?array
    {
        if ($column->filter('li')->count()) {
            return self::parseLabelledItems(self::extractItemsFromList($column));
        }

        // `<strong>` entries without list items; tier headers alone fall through to flat text.
        if (($items = self::extractItemsFromStrongs($column)) !== []) {
            return self::parseLabelledItems($items);
        }

        return self::parseFlatTier(
            preg_replace('/^(Minimum|Recommended):\s*/iu', '', self::normalizeSpace($column->text())),
            $platform,
        );
    }

    /** Extracts [label, value] pairs from `<li><strong>Label:</strong> …` entries. */
    private static function extractItemsFromList(Crawler $column): array
    {
        $items = [];

        foreach ($column->filter('li') as $node) {
            $item = new NativeCrawler($node);
            $text = self::normalizeSpace($item->text());

            if (!$item->filter('strong')->count()) {
                // Unlabelled entries are publisher notes (e.g. "Requires a 64-bit processor …").
                $items[] = [null, $text];

                continue;
            }

            $separator = strpos($text, ':');
            $items[] = [
                self::normalizeSpace($item->filter('strong')->eq(0)->text()),
                $separator === false ? $text : trim(substr($text, $separator + 1)),
            ];
        }

        return $items;
    }

    /**
     * Extracts [label, value] pairs from `<strong>Label:</strong> value<br> …` entries, where the
     * value is the text following each label up to the next label. Tier headers ("Minimum:",
     * "Recommended:") are skipped, so an empty result means the column holds flat text.
     */
    private static function extractItemsFromStrongs(Crawler $column): array
    {
        $items = [];

        foreach ($column->filter('strong') as $node) {
            $label = self::normalizeSpace($node->textContent);

            if (self::mapLabel($label) === null) {
                continue;
            }

            $valueParts = [];

            for ($sibling = $node->nextSibling; $sibling !== null; $sibling = $sibling->nextSibling) {
                if ($sibling instanceof \DOMElement && mb_strtolower($sibling->tagName) === 'strong') {
                    break;
                }

                if ($sibling instanceof \DOMText || $sibling instanceof \DOMElement) {
                    $text = self::normalizeSpace($sibling->textContent);

                    if ($text !== '') {
                        $valueParts[] = $text;
                    }
                }
            }

            $items[] = [$label, self::normalizeSpace(implode(' ', $valueParts))];
        }

        return $items;
    }

    /** Assembles a tier from [label, value] pairs, or null when no usable data was found. */
    private static function parseLabelledItems(array $items): ?array
    {
        $raw = ['os' => null, 'processor' => null, 'memory' => null, 'graphics' => null];
        $notes = [];

        foreach ($items as [$label, $value]) {
            if ($value === '') {
                continue;
            }

            $field = $label === null ? 'notes' : self::mapLabel($label);

            if ($field === null) {
                continue;
            }

            if ($field === 'notes') {
                $notes[] = $value;

                continue;
            }

            $raw[$field] ??= $value;
        }

        if ($raw === ['os' => null, 'processor' => null, 'memory' => null, 'graphics' => null]) {
            return null;
        }

        return self::buildTier($raw['os'], $raw['processor'], $raw['memory'], $raw['graphics'], $notes);
    }

    /**
     * Parses flat comma-separated requirements text ("500 mhz processor, 96mb ram, …") by
     * classifying each fragment. The split is on commas and semicolons outside parentheses, so a
     * parenthesized comma does not cut a fragment short ("Pentium 4 processor (3.0GHz, or better)").
     * Returns null when no fragment was recognized.
     */
    private static function parseFlatTier(string $text, string $platform = 'windows'): ?array
    {
        $os = $processor = $memory = $graphics = null;

        foreach (self::splitFragments($text, false) as $fragment) {
            $fragment = trim($fragment);

            if ($fragment === '') {
                continue;
            }

            if ($os === null && self::isOsFragment($fragment, $platform)) {
                $os = $fragment;
            } elseif ($processor === null && preg_match(self::PROCESSOR_FRAGMENT_PATTERN, $fragment)) {
                $processor = $fragment;
            } elseif ($graphics === null && self::isGraphicsFragment($fragment)) {
                $graphics = $fragment;
            } elseif ($memory === null && preg_match(self::MEMORY_FRAGMENT_PATTERN, $fragment)) {
                $memory = $fragment;
            }
        }

        if ($os === null && $processor === null && $memory === null && $graphics === null) {
            return null;
        }

        return self::buildTier($os, $processor, $memory, $graphics, []);
    }

    /** Whether a flat fragment describes an operating system rather than hardware. */
    private static function isOsFragment(string $fragment, string $platform): bool
    {
        if (preg_match('/^(os|operating system)\b/i', $fragment)
            || preg_match(self::OS_FRAGMENT_PATTERN, $fragment)
        ) {
            return true;
        }

        return match ($platform) {
            'mac' => (bool)preg_match(
                '/\b(mac|macos|os\s*x|snow leopard|lion|mountain lion|mavericks|yosemite|el capitan'
                . '|sierra|high sierra|mojave|catalina|big sur|monterey|ventura|sonoma|sequoia)\b/i',
                $fragment,
            ),
            'linux' => (bool)preg_match('/\b(linux|ubuntu|steamos|fedora|debian|mint)\b/i', $fragment),
            default => false,
        };
    }

    /** Whether a flat fragment describes graphics hardware rather than memory or another group. */
    private static function isGraphicsFragment(string $fragment): bool
    {
        return preg_match(self::GRAPHICS_FRAGMENT_PATTERN, $fragment)
            || preg_match(self::GPU_FAMILY_PATTERN, $fragment)
            || preg_match(self::GRAPHICS_MODEL_PATTERN, $fragment);
    }

    private static function buildTier(
        ?string $os,
        ?string $processor,
        ?string $memory,
        ?string $graphics,
        array $notes
    ): array {
        return [
            'os' => self::parseOs($os, $notes),
            'processor' => self::parseProcessor($processor),
            'memory' => ['raw' => $memory, 'mb' => $memory === null ? null : self::parseFirstSizeMb($memory)],
            'graphics' => self::parseGraphics($graphics, $notes),
        ];
    }

    private static function parseOs(?string $raw, array $notes): array
    {
        $windows = $raw === null ? [] : self::parseWindowsVersions($raw);

        $requires64Bit = $raw !== null && self::requires64Bit($raw);

        if (!$requires64Bit) {
            foreach ($notes as $note) {
                if (self::requires64Bit($note)) {
                    $requires64Bit = true;

                    break;
                }
            }
        }

        return [
            'raw' => $raw,
            'windows' => $windows,
            'minimum_windows' => $windows === [] ? null : $windows[0],
            'requires_64bit' => (bool)$requires64Bit,
        ];
    }

    /** Whether the text requires 64-bit, ignoring combined architecture lists ("32/64-bit"). */
    private static function requires64Bit(string $text): bool
    {
        // A "32/64-bit" mention offers both architectures, so it does not require 64-bit.
        $text = preg_replace('/\b32\b[^\d]{0,10}\b64\b/', '', $text);

        return (bool)preg_match('/64\s*-?\s*bit/i', $text);
    }

    /** Extracts mentioned Windows versions, oldest first. */
    private static function parseWindowsVersions(string $raw): array
    {
        if (!preg_match('/windows/i', $raw)) {
            return [];
        }

        $found = [];

        // Explicit "Windows <version>", tolerating encoding artifacts between the words.
        if (preg_match_all('/windows\W*(11|10|8\.1|8|7|vista|xp|2000|me|98)\b/i', $raw, $matches)) {
            foreach ($matches[1] as $version) {
                $normalized = self::normalizeWindowsVersion($version);

                if (!\in_array($normalized, $found, true)) {
                    $found[] = $normalized;
                }
            }
        }

        // Bare versions after separators ("7 / 8 / 10", "2000, XP or Vista").
        if (preg_match_all('/(?:\/|,|\bor\b)\s*(11|8\.1|10|8|7|vista|xp|2000|me|98)\b/i', $raw, $matches)) {
            foreach ($matches[1] as $version) {
                $normalized = self::normalizeWindowsVersion($version);

                if (!\in_array($normalized, $found, true)) {
                    $found[] = $normalized;
                }
            }
        }

        usort($found, static fn (string $a, string $b): int => self::WINDOWS_RANK[$a] <=> self::WINDOWS_RANK[$b]);

        return $found;
    }

    /** Canonicalises version casing to the ladder keys, keeping names like "Vista" title-cased. */
    private static function normalizeWindowsVersion(string $version): string
    {
        foreach (array_keys(self::WINDOWS_RANK) as $canonical) {
            if (strcasecmp((string)$canonical, $version) === 0) {
                return (string)$canonical;
            }
        }

        return $version;
    }

    private static function parseProcessor(?string $raw): array
    {
        if ($raw === null) {
            return ['raw' => null, 'speed_ghz' => null, 'cores' => null, 'threads' => null, 'models' => []];
        }

        $speeds = [];
        $speedTokens = [];

        // Speeds with explicit units ("1.2GHz", "500 mhz").
        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(GHZ|MHZ)/i', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $number = (float)str_replace(',', '.', $match[1]);
                $speeds[] = mb_strtoupper($match[2]) === 'MHZ' ? $number / 1000 : $number;
                $speedTokens[] = $match[0];
            }
        }

        // Bare decimals ("Dual Core 2.0") are clock speeds in GHz. Bare integers are not: they are
        // model numbers ("486", "E6300", "FX-6350").
        if (preg_match_all('/\b\d\.\d+\b/', $raw, $matches)) {
            foreach ($matches[0] as $token) {
                $alreadyCaptured = false;

                foreach ($speedTokens as $captured) {
                    if (str_contains($captured, $token)) {
                        $alreadyCaptured = true;

                        break;
                    }
                }

                if (!$alreadyCaptured) {
                    $speeds[] = (float)$token;
                    $speedTokens[] = $token;
                }
            }
        }

        $cores = [];

        if (preg_match_all('/(\d+)\s*-\s*core\b/i', $raw, $matches)) {
            foreach ($matches[1] as $count) {
                $cores[] = (int)$count;
            }
        }

        $coreWords = [
            'single' => 1,
            'dual' => 2,
            'tri(?:ple)?' => 3,
            'quad' => 4,
            'hexa' => 6,
            'octa' => 8,
        ];

        foreach ($coreWords as $word => $count) {
            if (preg_match("/\\b{$word}[\\s-]*cores?\\b/i", $raw)) {
                $cores[] = $count;
            }
        }

        $threads = preg_match('/(\d+)\s*(?:hardware\s+)?cpu\s*threads?\b/i', $raw, $matches)
            ? (int)$matches[1]
            : null;

        return [
            'raw' => $raw,
            'speed_ghz' => $speeds === [] ? null : min($speeds),
            'cores' => $cores === [] ? null : min($cores),
            'threads' => $threads,
            'models' => self::parseProcessorModels($raw, $speedTokens),
        ];
    }

    /** Splits alternatives and strips speeds, qualifiers and generic words, leaving model names. */
    private static function parseProcessorModels(string $raw, array $speedTokens): array
    {
        $models = [];

        foreach (self::splitFragments($raw) as $part) {
            $part = trim(preg_replace('/\([^()]*\)/', '', $part));
            $part = trim(preg_replace('/^\d+\s*(hardware\s+)?cpu\s*threads?\s*-\s*/i', '', $part));

            foreach ($speedTokens as $token) {
                $part = trim(str_ireplace($token, '', $part));
            }

            $part = trim(preg_replace('/\s+or\s+(' . self::QUALIFIERS . ')\b/i', '', $part));
            $part = trim(preg_replace('/\b(' . self::QUALIFIERS . '|minimum|required)\b/i', '', $part));
            $part = trim(preg_replace('/\s+(processor|cpu|apu)\s*$/i', '', $part));
            $part = trim(preg_replace('/\s*@\s*$/', '', $part));
            $part = trim(preg_replace('/\s+(from|at|with|and|or|on|using|via|for|to|the)\s*$/i', '', $part));
            $part = trim(preg_replace('/^(from|at|with|and|or|on|using|via|for|to|the)\s+/i', '', $part));
            $part = self::cleanModel($part);
            $part = self::canonicalizeModel($part);

            if ($part === '' || self::isGenericProcessor($part)) {
                continue;
            }

            $models[] = $part;
        }

        // Deduplicate case-insensitively, preserving the first spelling.
        $unique = [];

        foreach ($models as $model) {
            $unique[mb_strtolower($model)] ??= $model;
        }

        return array_values($unique);
    }

    private static function isGenericProcessor(string $part): bool
    {
        // Known families are models even without a number ("Intel Pentium").
        if (preg_match('/pentium|athlon|ryzen|xeon|celeron|phenom|opteron|threadripper/i', $part)) {
            return false;
        }

        // Strip vendor and topology words: anything left is a model name ("P4", "486", "i5-8400").
        // A leading number-dash is topology too ("4-Core").
        $remainder = preg_replace(self::GENERIC_PROCESSOR_WORDS_PATTERN, '', $part);
        $remainder = preg_replace('/\b(from|at|with|and|or|on|using|via|for|to|the)\b/i', '', $remainder);
        $remainder = trim(preg_replace('/^\d+\s*-\s*/', '', $remainder));

        return preg_replace('/[^a-z0-9]/i', '', $remainder) === '';
    }

    private static function parseGraphics(?string $raw, array $notes): array
    {
        if ($raw === null) {
            return ['raw' => null, 'vram_mb' => null, 'models' => []];
        }

        $vram = self::parseMinSizeMb($raw);

        // A "VRAM …" statement in the additional notes refines the graphics requirement.
        foreach ($notes as $note) {
            if (preg_match('/\bvram\b/i', $note) && ($size = self::parseMinSizeMb($note)) !== null) {
                $vram = $vram === null ? $size : min($vram, $size);
            }
        }

        return ['raw' => $raw, 'vram_mb' => $vram, 'models' => self::parseGraphicsModels($raw)];
    }

    private static function parseGraphicsModels(string $raw): array
    {
        // Alternatives grouped in parentheses carry the models; the preamble is generic.
        if (preg_match('/\(([^()]*(?:\/|\bor\b)[^()]*)\)/', $raw, $matches)) {
            $source = $matches[1];
        } else {
            $source = preg_replace('/\([^()]*\)/', '', $raw);
        }

        $models = [];

        foreach (self::splitFragments($source) as $part) {
            $part = trim(preg_replace('/\([^()]*\)/', '', $part));
            $part = trim(preg_replace('/^(amd|nvidia|intel)\s*:\s*/i', '', $part));
            $part = trim(preg_replace('/\b\d+(?:[.,]\d+)?\s*(GB|MB)\b/i', '', $part));
            $part = trim(preg_replace('/\s+or\s+(higher|better|faster|equivalent|similar|greater)\b.*/i', '', $part));
            $part = trim(preg_replace('/\s+with\s+.*$/i', '', $part));
            $part = trim(preg_replace('/\bdirectx\b.*/i', '', $part));
            $part = trim(preg_replace('/\b3d\b/i', '', $part));
            $part = trim(preg_replace('/^(video|graphics|display)\s+cards?\b/i', '', $part));
            $part = self::cleanModel($part);
            $part = self::canonicalizeModel($part);

            if ($part === '' || self::isGenericGraphics($part)) {
                continue;
            }

            $models[] = $part;
        }

        $unique = [];

        foreach ($models as $model) {
            $unique[mb_strtolower($model)] ??= $model;
        }

        return array_values($unique);
    }

    private static function isGenericGraphics(string $part): bool
    {
        // A bare vendor name is not a model, even though it contains a known family.
        if (preg_match('/^(ati|amd|nvidia|intel|apple)$/i', $part)) {
            return true;
        }

        // Known GPU families or series prefixes with numbers ("GTX 1060", "RX 470").
        if (preg_match(self::GPU_FAMILY_PATTERN, $part)) {
            return false;
        }

        if (preg_match('/\b(gt|gtx|rtx|rx|r9|r7|hd)\s*\d/i', $part)) {
            return false;
        }

        // A number in a graphics context ("NVIDIA 8600M", "Intel Integrated 520").
        if (preg_match('/\d/', $part)
            && preg_match('/\b(graphics|geforce|radeon|nvidia|amd|intel|adapter|chip|cards?)\b/i', $part)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Splits requirement text on "/", ";", "," and the word "or" outside parentheses, so qualifiers
     * like "(or higher)" are never split apart. With `$alternatives` disabled only commas and
     * semicolons split, keeping version lists inside a fragment ("Windows 7 / Vista / XP").
     *
     * @return string[]
     */
    private static function splitFragments(string $text, bool $alternatives = true): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $length = mb_strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $char = mb_substr($text, $offset, 1);

            if ($char === '(') {
                $depth++;
                $current .= $char;
                $offset++;

                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $current .= $char;
                $offset++;

                continue;
            }

            if ($depth === 0 && ($char === ';' || $char === ',' || ($alternatives && $char === '/'))) {
                $parts[] = $current;
                $current = '';
                $offset++;

                continue;
            }

            if ($alternatives
                && $depth === 0
                && preg_match('/^or(?=\s|$)/i', mb_substr($text, $offset))
                && ($current === '' || preg_match('/\s$/', $current))
            ) {
                $parts[] = $current;
                $current = '';
                $offset += 2;

                continue;
            }

            $current .= $char;
            $offset++;
        }

        $parts[] = $current;

        return $parts;
    }

    /** Removes trademark symbols left over in model names ("Intel® Core™ i5"). */
    private static function cleanModel(string $model): string
    {
        return self::normalizeSpace(str_replace(['®', '™', '©', '�'], '', $model));
    }

    /** Expands a known shorthand to its canonical model name ("P4" → "Pentium 4"). */
    private static function canonicalizeModel(string $model): string
    {
        return self::MODEL_ALIASES[mb_strtolower($model)] ?? $model;
    }

    /** First memory size in the string, in mebibytes. */
    private static function parseFirstSizeMb(string $text): ?int
    {
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(TB|GB|MB|KB)\b/i', $text, $matches)) {
            return null;
        }

        return self::convertToMb($matches[1], mb_strtoupper($matches[2]));
    }

    /** Smallest memory size in the string, in mebibytes. */
    private static function parseMinSizeMb(string $text): ?int
    {
        if (!preg_match_all('/(\d+(?:[.,]\d+)?)\s*(TB|GB|MB|KB)\b/i', $text, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $sizes = array_map(
            static fn (array $match): int => self::convertToMb($match[1], mb_strtoupper($match[2])),
            $matches
        );

        return min($sizes);
    }

    private static function convertToMb(string $number, string $unit): int
    {
        $number = (float)str_replace(',', '', $number);

        return (int)round(match ($unit) {
            'KB' => $number / 1024,
            'MB' => $number,
            'GB' => $number * 1024,
            'TB' => $number * 1024 * 1024,
        });
    }

    /** Maps a normalized list label to a requirement group, notes bucket or null (ignored). */
    private static function mapLabel(string $label): ?string
    {
        return match (self::normalizeLabel($label)) {
            'os', 'os version', 'operating system' => 'os',
            'processor', 'cpu', 'cpu processor' => 'processor',
            'memory', 'ram' => 'memory',
            'graphics', 'video card', 'video memory', 'gpu' => 'graphics',
            'additional', 'additional notes', 'note', 'notes' => 'notes',
            default => null,
        };
    }

    private static function normalizeLabel(string $label): string
    {
        return self::normalizeSpace(mb_strtolower(preg_replace('/[^a-z0-9 ]/i', '', $label)));
    }

    private static function normalizeSpace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
