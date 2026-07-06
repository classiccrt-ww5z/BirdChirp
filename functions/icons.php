<?php
function svg_icon($name, $class = '', $size = 18, $style = 'vertical-align:middle') {
    $cls = $class ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
    $sty = $style ? ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"' : '';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 8 8" fill="currentColor"' . $cls . $sty . '><use href="/images/icons/open-iconic.svg#' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"/></svg>';
}
