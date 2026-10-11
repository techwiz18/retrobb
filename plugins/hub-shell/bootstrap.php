<?php
declare(strict_types=1);
// Hub shell: shared masthead + footer with the techwiz.dad hub so the
// forum feels like the same site. Markup mirrors the hub's Base.astro
// layout (header.site-header / footer.site-footer); refine against the
// hub side if that layout changes.
//
// Strategy: PREPEND the hub masthead above the forum topbar (forum
// login/userbox, mod queue, and skin switcher stay fully functional
// below it) and REPLACE the stock footer with the hub footer.
// Full header replacement is possible via the 'show_default_header'
// filter, but then this plugin must re-render the auth-dependent userbox
// itself — not worth it for v1.
//
// Skin/theme follow (both directions, hub proxy only — localStorage is
// per-origin, so direct subdomain visits keep their own values):
// hub and forum share localStorage under the proxy origin. The bridge
// below reconciles on every forum page load: an explicit choice stored
// locally wins and is mirrored into the forum cookies (so the server
// agrees on next render); with nothing stored, the server's values seed
// the store so the hub follows the forum. Clicks on either switcher UI
// update the store synchronously before navigation/paint.
use RetroBB\Core\Hooks;

Hooks::add_action('head', static function () {
    echo '<style>'
        . '.site-header{background:#1c2b4a;color:#f5e9b8;margin:0 0 16px;font-size:15px;line-height:1.6}'
        . '.site-header .wrap{max-width:860px;margin:0 auto;padding:0 16px}'
        . '.site-header .head-main{display:flex;align-items:center;gap:14px;padding:10px 16px;flex-wrap:wrap}'
        . '.site-header .head-util{display:flex;justify-content:flex-end;padding:0 16px 8px}'
        . '.site-header .brand{display:flex;align-items:center;gap:8px;color:#f5e9b8;text-decoration:none;font-weight:bold;font-size:16px}'
        . '.site-header .brand .floppy{display:inline-block;width:15px;height:15px;background:#7ea4d6;border:2px solid #dfe9f7;border-top-width:4px;border-radius:2px}'
        . '.site-header .mainnav{display:flex;gap:14px;margin-left:auto;align-items:center}'
        . '.site-header .mainnav a{color:#f5e9b8;font-size:13px;text-decoration:none}'
        . '.site-header .mainnav a:hover{text-decoration:underline}'
        . '.site-header .switchers{display:flex;gap:14px;align-items:center;font-size:12px;opacity:.75}'
        . '.site-header .switchers .grp button{font:inherit;font-size:12px;cursor:pointer;background:transparent;border:0;padding:0;margin-left:6px;color:inherit;opacity:.6;text-decoration:underline}'
        . ".site-header .switchers .grp button[aria-pressed='true']{opacity:1;font-weight:bold;text-decoration:none}"
        . '.site-footer{font-size:12px;color:#667;padding:22px 16px 26px;text-align:center}'
        . '.site-footer a{color:#1a3f7a}'
        . 'body.theme-dark .site-footer{color:#93a0b4}'
        . 'body.theme-dark .site-footer a{color:#9dbdea}'
        // Palette follow: hub tokens → forum surfaces, all skins. theme-auto
        // resolves via media query (not a class swap), so it is wired to the
        // light values with a dark override below.
        . 'body.skin-classic.theme-light,body.skin-classic.theme-auto{--bg:#dfe6f0;--panel:#f4f6fa;--border:#5b7fb4;--title-a:#3d639b;--title-b:#6f97c8;'
        . '--row:#f4f6fa;--row2:#e6ecf5;--link:#1a3f7a;--muted:#5a6577;--text:#1e2430;'
        . '--bar-bg:#e9eef6;--sub-bg:#eef2f8;--sub-text:#445068;--head-a:#e8eef6;--head-b:#d3ddee;'
        . '--quote-bg:#f4f6fa;--input-bg:#fff;--input-text:#1e2430;--btn-a:#fff;--btn-b:#cfd9e8;--btn-text:#1a3f7a}'
        . 'body.skin-classic.theme-dark{--bg:#141b2c;--panel:#1b2740;--border:#4a6ea0;--title-a:#2b456e;--title-b:#4a6ea0;'
        . '--row:#1b2740;--row2:#141b2c;--link:#9dbdea;--muted:#93a0b4;--text:#dbe3f0;'
        . '--bar-bg:#101827;--sub-bg:#141b2c;--sub-text:#93a0b4;--head-a:#4a6ea0;--head-b:#2b456e;'
        . '--quote-bg:#141b2c;--input-bg:#101827;--input-text:#dbe3f0;--btn-a:#4a6ea0;--btn-b:#2b456e;--btn-text:#fff}'
        . 'body.skin-midnight.theme-light,body.skin-midnight.theme-auto{--bg:#d8e0ec;--panel:#fff;--border:#2f4a75;--title-a:#2b456e;--title-b:#4a6ea0;'
        . '--row:#fff;--row2:#eef2f8;--link:#1a3f7a;--muted:#5a6577;--text:#1e2430;'
        . '--bar-bg:#e8edf5;--sub-bg:#f2f5fa;--sub-text:#445068;--head-a:#e8edf5;--head-b:#d5deee;'
        . '--quote-bg:#fff;--input-bg:#fff;--input-text:#1e2430;--btn-a:#fff;--btn-b:#cfd9e8;--btn-text:#1a3f7a}'
        . 'body.skin-midnight.theme-dark{--bg:#05080f;--panel:#0d1526;--border:#3f6cb3;--title-a:#274b85;--title-b:#3f7ad0;'
        . '--row:#0d1526;--row2:#05080f;--link:#8fb6f2;--muted:#8b96ab;--text:#d6e0f2;'
        . '--bar-bg:#05080f;--sub-bg:#05080f;--sub-text:#8b96ab;--head-a:#3f7ad0;--head-b:#274b85;'
        . '--quote-bg:#05080f;--input-bg:#0d1526;--input-text:#d6e0f2;--btn-a:#3f7ad0;--btn-b:#274b85;--btn-text:#eaf1ff}'
        . 'body.skin-silver.theme-light,body.skin-silver.theme-auto{--bg:#e8ecf2;--panel:#f7f8fb;--border:#8b98ad;--title-a:#7e8ba0;--title-b:#b9c3d4;'
        . '--row:#f7f8fb;--row2:#edf0f5;--link:#2c4a75;--muted:#67727f;--text:#232a36;'
        . '--bar-bg:#eef1f6;--sub-bg:#f2f4f8;--sub-text:#4c565f;--head-a:#eef1f6;--head-b:#d9dfe9;'
        . '--quote-bg:#f7f8fb;--input-bg:#fff;--input-text:#232a36;--btn-a:#fff;--btn-b:#d3d9e3;--btn-text:#2c4a75}'
        . 'body.skin-silver.theme-dark{--bg:#101216;--panel:#1a1f29;--border:#6e7b91;--title-a:#5b667c;--title-b:#8e9cb5;'
        . '--row:#1a1f29;--row2:#101216;--link:#a9c3ea;--muted:#8e99ab;--text:#d3dae6;'
        . '--bar-bg:#0b0e14;--sub-bg:#101216;--sub-text:#8e99ab;--head-a:#8e9cb5;--head-b:#5b667c;'
        . '--quote-bg:#101216;--input-bg:#1a1f29;--input-text:#d3dae6;--btn-a:#8e9cb5;--btn-b:#5b667c;--btn-text:#f2f4f8}'
        . '@media (prefers-color-scheme:dark){'
        . 'body.skin-classic.theme-auto{--bg:#141b2c;--panel:#1b2740;--border:#4a6ea0;--title-a:#2b456e;--title-b:#4a6ea0;'
        . '--row:#1b2740;--row2:#141b2c;--link:#9dbdea;--muted:#93a0b4;--text:#dbe3f0;'
        . '--bar-bg:#101827;--sub-bg:#141b2c;--sub-text:#93a0b4;--head-a:#4a6ea0;--head-b:#2b456e;'
        . '--quote-bg:#141b2c;--input-bg:#101827;--input-text:#dbe3f0;--btn-a:#4a6ea0;--btn-b:#2b456e;--btn-text:#fff}'
        . 'body.skin-midnight.theme-auto{--bg:#05080f;--panel:#0d1526;--border:#3f6cb3;--title-a:#274b85;--title-b:#3f7ad0;'
        . '--row:#0d1526;--row2:#05080f;--link:#8fb6f2;--muted:#8b96ab;--text:#d6e0f2;'
        . '--bar-bg:#05080f;--sub-bg:#05080f;--sub-text:#8b96ab;--head-a:#3f7ad0;--head-b:#274b85;'
        . '--quote-bg:#05080f;--input-bg:#0d1526;--input-text:#d6e0f2;--btn-a:#3f7ad0;--btn-b:#274b85;--btn-text:#eaf1ff}'
        . 'body.skin-silver.theme-auto{--bg:#101216;--panel:#1a1f29;--border:#6e7b91;--title-a:#5b667c;--title-b:#8e9cb5;'
        . '--row:#1a1f29;--row2:#101216;--link:#a9c3ea;--muted:#8e99ab;--text:#d3dae6;'
        . '--bar-bg:#0b0e14;--sub-bg:#101216;--sub-text:#8e99ab;--head-a:#8e9cb5;--head-b:#5b667c;'
        . '--quote-bg:#101216;--input-bg:#1a1f29;--input-text:#d3dae6;--btn-a:#8e9cb5;--btn-b:#5b667c;--btn-text:#f2f4f8}}'
        // Hardcoded surfaces the variable overrides cannot reach: retint the
        // forum topbar / category bars from the mapped title gradient (hub
        // bars use the same bar-from → bar-to shape), follow the hub header
        // background per skin + theme, and mute hardcoded small grey text.
        . '.topbar{background:linear-gradient(180deg,var(--title-b),var(--title-a))}'
        . '.cat-row{background:linear-gradient(180deg,var(--title-b),var(--cat))}'
        . '.postdate,.empty{color:var(--muted)}'
        . 'body.skin-classic .site-header{background:#1c2b4a}'
        . 'body.skin-midnight .site-header{background:#0a0f1c}'
        . 'body.skin-silver .site-header{background:#1c2b4a}'
        . 'body.skin-classic.theme-dark .site-header{background:#101827}'
        . 'body.skin-midnight.theme-dark .site-header{background:#05080f}'
        . 'body.skin-silver.theme-dark .site-header{background:#0b0e14}'
        . '@media (prefers-color-scheme:dark){'
        . 'body.skin-classic.theme-auto .site-header{background:#101827}'
        . 'body.skin-midnight.theme-auto .site-header{background:#05080f}'
        . 'body.skin-silver.theme-auto .site-header{background:#0b0e14}}'
        . '@media(max-width:640px){.site-header .mainnav{margin-left:0}}'
        . '</style>';
});

Hooks::add_action('header', static function () {
    $skinBtns = '';
    if (feature('skin_selector')) {
        foreach (['classic', 'midnight', 'silver'] as $s) {
            $skinBtns .= '<button data-skin-btn="' . $s . '">' . ucfirst($s) . '</button>';
        }
    }
    $themeBtns = '';
    foreach (allowed_themes() as $tm) {
        $themeBtns .= '<button data-theme-btn="' . $tm . '">' . ucfirst($tm) . '</button>';
    }
    echo '<header class="site-header"><div class="wrap head-main">'
        . '<a class="brand" href="https://techwiz.dad"><span class="floppy" aria-hidden="true"></span> techwiz.dad</a>'
        . '<nav class="mainnav" aria-label="Main">'
        . '<a href="https://techwiz.dad/projects/">Projects</a>'
        . '<a href="https://github.com/techwiz18" target="_blank" rel="noopener">GitHub</a>'
        . '<a href="https://techwiz.dad/forum/">Forum</a>'
        . '</nav></div>'
        . '<div class="wrap head-util"><div class="switchers" role="group" aria-label="Skin and theme">'
        . '<span class="grp">Skin:' . $skinBtns . '</span>'
        . '<span class="grp">Theme:' . $themeBtns . '</span>'
        . '</div></div></header>';
    echo '<script>(function(){try{'
        . 'var body=document.body,ls=null,lt=null;'
        . 'try{ls=localStorage.getItem("hub-skin");lt=localStorage.getItem("hub-theme");}catch(e){}'
        . 'var m=body.className.match(/skin-([a-z]+)/),m2=body.className.match(/theme-(light|dark|auto)/);'
        . 'var curSkin=m?m[1]:"classic",curTheme=m2?m2[1]:"auto";'
        . 'var skins=[],themes=[];'
        . 'document.querySelectorAll("[data-skin-btn]").forEach(function(b){skins.push(b.getAttribute("data-skin-btn"));});'
        . 'document.querySelectorAll("[data-theme-btn]").forEach(function(b){themes.push(b.getAttribute("data-theme-btn"));});'
        . 'var skin=(ls&&skins.indexOf(ls)>=0)?ls:curSkin;'
        . 'var theme=(lt&&themes.indexOf(lt)>=0)?lt:curTheme;'
        . 'if(skin!==curSkin){body.className=body.className.replace(/skin-[a-z]+/,"skin-"+skin);setCookie("retrobb_skin",skin);}'
        . 'if(theme!==curTheme){body.className=body.className.replace(/theme-(light|dark|auto)/,"theme-"+theme);setCookie("retrobb_theme",theme);}'
        . 'setStore("hub-skin",skin);setStore("hub-theme",theme);paint();'
        . 'function onPick(e){'
        . 'var s=e.target.closest?e.target.closest("[data-skin-btn]"):null;'
        . 'var t=e.target.closest?e.target.closest("[data-theme-btn]"):null;'
        . 'var a=e.target.closest?e.target.closest("a[href]"):null;'
        . 'if(a&&a.href){var hm=a.href.match(/\\/(skin|theme)\\/([a-z]+)/);if(hm){if(hm[1]==="skin"){setStore("hub-skin",hm[2]);setCookie("retrobb_skin",hm[2]);}else{setStore("hub-theme",hm[2]);setCookie("retrobb_theme",hm[2]);}}}'
        . 'if(s){var sv=s.getAttribute("data-skin-btn");setStore("hub-skin",sv);setCookie("retrobb_skin",sv);'
        . 'body.className=body.className.replace(/skin-[a-z]+/,"skin-"+sv);skin=sv;paint();}'
        . 'if(t){var tv=t.getAttribute("data-theme-btn");setStore("hub-theme",tv);setCookie("retrobb_theme",tv);'
        . 'body.className=body.className.replace(/theme-(light|dark|auto)/,"theme-"+tv);theme=tv;paint();}'
        . '}'
        . 'document.addEventListener("click",onPick);'
        . 'document.addEventListener("auxclick",onPick);'
        . 'function paint(){'
        . 'document.querySelectorAll("[data-skin-btn]").forEach(function(b){b.setAttribute("aria-pressed",b.getAttribute("data-skin-btn")===skin?"true":"false");});'
        . 'document.querySelectorAll("[data-theme-btn]").forEach(function(b){b.setAttribute("aria-pressed",b.getAttribute("data-theme-btn")===theme?"true":"false");});}'
        . 'function setStore(k,v){try{localStorage.setItem(k,v);}catch(e){}}'
        . 'function setCookie(k,v){try{var p=(location.pathname==="/forum"||location.pathname.indexOf("/forum/")===0)?"/forum":"/";'
        . 'document.cookie=k+"="+v+";path="+p+";max-age=31536000;SameSite=Lax";}catch(e){}}'
        . '}catch(e){}})();</script>';
});

Hooks::add_filter('show_default_footer', static fn() => false);

Hooks::add_action('footer', static function () {
    echo '<div>Powered by curiosity · <strong>techwiz.dad</strong> · Old-school soul, modern code · ' . date('Y') . '</div>';
    echo '<div>Hub is static · Forum lives at <a href="https://techwiz.dad/forum/">https://techwiz.dad/forum/</a></div>';
});
