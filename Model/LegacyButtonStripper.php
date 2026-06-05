<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model;

/**
 * Removes legacy hardcoded CTA "button" anchors from description HTML:
 *   - variant buttons ("VIEW OTHER OPTIONS / FINISHES / SIZES"), and
 *   - any anchor styled as a button (inline style includes border-radius),
 *     e.g. red "VIEW CYLINDER LOCKS" cross-links baked into short_description.
 * Robust to MALFORMED buttons whose <a> tag is missing its closing '>'
 * (the old export ran the unquoted href straight into the link text).
 */
class LegacyButtonStripper
{
    public function strip(?string $html): string
    {
        if ($html === null || $html === '') {
            return (string) $html;
        }

        // 1) Well-formed variant buttons.
        $html = preg_replace(
            '~<a\b[^>]*>\s*(?:&nbsp;|\s)*VIEW\s+OTHER\s+(?:OPTIONS|FINISHES|SIZES)(?:&nbsp;|\s)*\s*</a>~is',
            '',
            (string) $html
        );

        // 2) Any anchor hardcoded as a CTA button — border-radius in the opening
        //    tag, then anything up to </a>. Does NOT require the tag's '>' (so it
        //    also catches malformed buttons + quoted styles).
        $html = preg_replace(
            '~<a\b[^>]*?border-radius.*?</a>~is',
            '',
            (string) $html
        );

        // 3) Tidy empty <p> wrappers left behind.
        $html = preg_replace('~<p[^>]*>\s*(?:&nbsp;|\s)*</p>~is', '', (string) $html);

        return (string) $html;
    }
}
