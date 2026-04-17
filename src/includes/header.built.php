<?php
/**
 * Header component with HTML head and CSS styles
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP File Browser</title>
    <style>
        /* POFF_STYLE_START */
/* layer: preflights */
*,::before,::after{--un-rotate:0;--un-rotate-x:0;--un-rotate-y:0;--un-rotate-z:0;--un-scale-x:1;--un-scale-y:1;--un-scale-z:1;--un-skew-x:0;--un-skew-y:0;--un-translate-x:0;--un-translate-y:0;--un-translate-z:0;--un-pan-x: ;--un-pan-y: ;--un-pinch-zoom: ;--un-scroll-snap-strictness:proximity;--un-ordinal: ;--un-slashed-zero: ;--un-numeric-figure: ;--un-numeric-spacing: ;--un-numeric-fraction: ;--un-border-spacing-x:0;--un-border-spacing-y:0;--un-ring-offset-shadow:0 0 rgb(0 0 0 / 0);--un-ring-shadow:0 0 rgb(0 0 0 / 0);--un-shadow-inset: ;--un-shadow:0 0 rgb(0 0 0 / 0);--un-ring-inset: ;--un-ring-offset-width:0px;--un-ring-offset-color:#fff;--un-ring-width:0px;--un-ring-color:rgb(147 197 253 / 0.5);--un-blur: ;--un-brightness: ;--un-contrast: ;--un-drop-shadow: ;--un-grayscale: ;--un-hue-rotate: ;--un-invert: ;--un-saturate: ;--un-sepia: ;--un-backdrop-blur: ;--un-backdrop-brightness: ;--un-backdrop-contrast: ;--un-backdrop-grayscale: ;--un-backdrop-hue-rotate: ;--un-backdrop-invert: ;--un-backdrop-opacity: ;--un-backdrop-saturate: ;--un-backdrop-sepia: ;}::backdrop{--un-rotate:0;--un-rotate-x:0;--un-rotate-y:0;--un-rotate-z:0;--un-scale-x:1;--un-scale-y:1;--un-scale-z:1;--un-skew-x:0;--un-skew-y:0;--un-translate-x:0;--un-translate-y:0;--un-translate-z:0;--un-pan-x: ;--un-pan-y: ;--un-pinch-zoom: ;--un-scroll-snap-strictness:proximity;--un-ordinal: ;--un-slashed-zero: ;--un-numeric-figure: ;--un-numeric-spacing: ;--un-numeric-fraction: ;--un-border-spacing-x:0;--un-border-spacing-y:0;--un-ring-offset-shadow:0 0 rgb(0 0 0 / 0);--un-ring-shadow:0 0 rgb(0 0 0 / 0);--un-shadow-inset: ;--un-shadow:0 0 rgb(0 0 0 / 0);--un-ring-inset: ;--un-ring-offset-width:0px;--un-ring-offset-color:#fff;--un-ring-width:0px;--un-ring-color:rgb(147 197 253 / 0.5);--un-blur: ;--un-brightness: ;--un-contrast: ;--un-drop-shadow: ;--un-grayscale: ;--un-hue-rotate: ;--un-invert: ;--un-saturate: ;--un-sepia: ;--un-backdrop-blur: ;--un-backdrop-brightness: ;--un-backdrop-contrast: ;--un-backdrop-grayscale: ;--un-backdrop-hue-rotate: ;--un-backdrop-invert: ;--un-backdrop-opacity: ;--un-backdrop-saturate: ;--un-backdrop-sepia: ;}

        html, body {
          height: 100%;
        }
        body {
          margin: 0;
          padding: 0;
          overflow: auto;
          font-family: Inter, sans-serif;
          background-color: #f0f2f5;
        }
      
/* layer: shortcuts */
.edit-drawer{position:absolute;top:0;right:0;bottom:0;z-index:30;width:360px;--un-translate-x:100%;transform:translateX(var(--un-translate-x)) translateY(var(--un-translate-y)) translateZ(var(--un-translate-z)) rotate(var(--un-rotate)) rotateX(var(--un-rotate-x)) rotateY(var(--un-rotate-y)) rotateZ(var(--un-rotate-z)) skewX(var(--un-skew-x)) skewY(var(--un-skew-y)) scaleX(var(--un-scale-x)) scaleY(var(--un-scale-y)) scaleZ(var(--un-scale-z));overflow:auto;border-left-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));--un-bg-opacity:1;background-color:rgb(255 255 255 / var(--un-bg-opacity));padding:1rem;--un-shadow:-8px 0 18px var(--un-shadow-color, rgba(15, 23, 42, 0.08));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);transition-property:transform;transition-timing-function:cubic-bezier(0.4, 0, 0.2, 1);transition-duration:150ms;transition-duration:200ms;}
.main-content{position:relative;height:100vh;display:flex;flex:1 1 0%;flex-direction:column;overflow-y:auto;--un-bg-opacity:1;background-color:rgb(255 255 255 / var(--un-bg-opacity));}
.edit-form,
.edit-grid,
.edit-inline{display:grid;gap:0.75rem;}
.prompt-window{display:grid;margin-top:0.625rem;gap:0.75rem;border-top-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));border-style:dashed;padding-top:0.375rem;}
.edit-grid-cols{grid-template-columns:repeat(auto-fit,minmax(220px,1fr));}
.prompt-grid{grid-template-columns:repeat(auto-fit,minmax(180px,1fr));}
.drawer-title{margin:0;font-size:1em;--un-text-opacity:1;color:rgb(17 24 39 / var(--un-text-opacity));font-weight:600;}
.edit-panel-title{margin:0;margin-bottom:0.375rem;font-size:1.05em;--un-text-opacity:1;color:rgb(17 24 39 / var(--un-text-opacity));font-weight:600;}
.nav-list{margin:0;list-style-type:none;padding:0;}
.prompt-inline-toggle-input{margin:0;}
.drawer-header{margin-bottom:0.5rem;display:flex;align-items:center;justify-content:space-between;}
.edit-label{margin-bottom:0.25rem;display:block;font-size:0.85em;--un-text-opacity:1;color:rgb(55 65 81 / var(--un-text-opacity));}
.edit-status{margin-bottom:0.5rem;font-size:0.875rem;line-height:1.25rem;--un-text-opacity:1;color:rgb(185 28 28 / var(--un-text-opacity));}
.item-icon{margin-right:0.625rem;width:18px;height:18px;flex-shrink:0;}
.loader-label{margin-left:0.5rem;}
.nav-link{margin-bottom:0.375rem;display:flex;align-items:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-radius:0.375rem;padding-left:0.75rem;padding-right:0.75rem;padding-top:0.5rem;padding-bottom:0.5rem;--un-text-opacity:1;color:rgb(55 65 81 / var(--un-text-opacity));text-decoration:none;transition-property:color,background-color,border-color,text-decoration-color,fill,stroke;transition-timing-function:cubic-bezier(0.4, 0, 0.2, 1);transition-duration:150ms;transition-duration:200ms;}
.prompt-context-title{margin-bottom:0.25rem;--un-text-opacity:1;color:rgb(17 24 39 / var(--un-text-opacity));font-weight:600;}
.prompt-inline{margin-top:1rem;border-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));border-radius:0.75rem;--un-bg-opacity:1;background-color:rgb(249 250 251 / var(--un-bg-opacity));padding:0.75rem;--un-shadow:0 10px 30px var(--un-shadow-color, rgba(15, 23, 42, 0.06));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.prompt-message{margin-bottom:0.5rem;border-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));border-radius:0.375rem;--un-bg-opacity:1;background-color:rgb(255 255 255 / var(--un-bg-opacity));padding:0.375rem;padding-left:0.5rem;padding-right:0.5rem;}
.prompt-message-role{margin-right:0.375rem;font-weight:600;}
.prompt-settings-actions{margin-top:-0.375rem;display:flex;justify-content:flex-end;}
.prompt-summary-title{margin-bottom:0.375rem;--un-text-opacity:1;color:rgb(17 24 39 / var(--un-text-opacity));font-weight:700;}
.prompt-system-footer{margin-top:0.375rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.625rem;}
.prompt-system-summary{margin-bottom:0.375rem;cursor:pointer;font-weight:600;}
.prompt-textarea{margin-top:0.375rem;min-height:90px;}
.stream-cursor{margin-left:0.125rem;display:inline-block;width:0.5rem;height:14px;--un-bg-opacity:1;background-color:rgb(156 163 175 / var(--un-bg-opacity));}
.prompt-message:last-child{margin-bottom:0;}
.form-input{box-sizing:border-box;width:100%;border-width:1px;--un-border-opacity:1;border-color:rgb(209 213 219 / var(--un-border-opacity));border-radius:0.375rem;padding-left:0.625rem;padding-right:0.625rem;padding-top:0.5rem;padding-bottom:0.5rem;font-size:0.95em;}
.form-textarea{box-sizing:border-box;width:100%;min-height:72px;resize:vertical;border-width:1px;--un-border-opacity:1;border-color:rgb(209 213 219 / var(--un-border-opacity));border-radius:0.375rem;padding-left:0.625rem;padding-right:0.625rem;padding-top:0.5rem;padding-bottom:0.5rem;font-size:0.95em;}
.prompt-input{box-sizing:border-box;width:100%;min-height:72px;min-height:90px;resize:vertical;border-width:1px;--un-border-opacity:1;border-color:rgb(209 213 219 / var(--un-border-opacity));border-radius:0.375rem;padding-left:0.625rem;padding-right:0.625rem;padding-top:0.5rem;padding-bottom:0.5rem;font-size:0.95em;}
.loader{display:inline-block;width:1.5rem;height:1.5rem;animation:spin 1s linear infinite;border-width:4px;--un-border-opacity:1;border-color:rgb(59 130 246 / var(--un-border-opacity));--un-border-top-opacity:var(--un-border-opacity);border-top-color:rgb(229 231 235 / var(--un-border-top-opacity));border-radius:9999px;}
.loading-row{display:none;padding:0.625rem;text-align:center;}
.container{height:100vh;display:flex;}
.content-frame{min-height:60vh;flex:1 1 0%;border-width:0px;padding-bottom:1.5rem;}
.edit-toggle{width:100%;cursor:pointer;border-width:1px;--un-border-opacity:1;border-color:rgb(17 24 39 / var(--un-border-opacity));border-radius:0.5rem;--un-bg-opacity:1;background-color:rgb(17 24 39 / var(--un-bg-opacity));padding-left:0.75rem;padding-right:0.75rem;padding-top:0.625rem;padding-bottom:0.625rem;--un-text-opacity:1;color:rgb(255 255 255 / var(--un-text-opacity));font-weight:600;transition-property:color,background-color,border-color,text-decoration-color,fill,stroke;transition-timing-function:cubic-bezier(0.4, 0, 0.2, 1);transition-duration:150ms;transition-duration:200ms;}
.edit-tree{max-height:220px;overflow:auto;border-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));border-radius:0.375rem;--un-bg-opacity:1;background-color:rgb(255 255 255 / var(--un-bg-opacity));padding:0.625rem;}
.prompt-dot{width:0.625rem;height:0.625rem;border-radius:9999px;--un-bg-opacity:1;background-color:rgb(5 150 105 / var(--un-bg-opacity));--un-shadow:0 0 0 4px var(--un-shadow-color, rgba(22, 163, 74, 0.15));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.prompt-messages{max-height:160px;overflow:auto;border-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));border-radius:0.5rem;--un-bg-opacity:1;background-color:rgb(248 250 252 / var(--un-bg-opacity));padding:0.625rem;font-size:0.9em;--un-shadow:inset 0 1px 0 var(--un-shadow-color, rgba(255, 255, 255, 0.4));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.sidebar{width:280px;flex-shrink:0;overflow-y:auto;border-right-width:1px;--un-border-opacity:1;border-color:rgb(209 213 219 / var(--un-border-opacity));--un-bg-opacity:1;background-color:rgb(255 255 255 / var(--un-bg-opacity));padding:1.25rem;--un-shadow:2px 0 8px var(--un-shadow-color, rgba(0, 0, 0, 0.05));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.edit-actions{display:flex;align-items:center;gap:0.75rem;}
.edit-inline-actions{display:flex;align-items:center;gap:0.625rem;}
.edit-tree-item{display:flex;align-items:center;gap:0.5rem;padding-top:0.25rem;padding-bottom:0.25rem;font-size:0.9em;--un-text-opacity:1;color:rgb(55 65 81 / var(--un-text-opacity));}
.prompt-actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.5rem;}
.prompt-actions-left{display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;}
.prompt-allowed{display:flex;align-items:center;gap:0.5rem;border-width:1px;--un-border-opacity:1;border-color:rgb(167 243 208 / var(--un-border-opacity));border-radius:0.5rem;--un-bg-opacity:1;background-color:rgb(236 253 245 / var(--un-bg-opacity));padding-left:0.625rem;padding-right:0.625rem;padding-top:0.5rem;padding-bottom:0.5rem;font-size:0.9em;--un-text-opacity:1;color:rgb(4 120 87 / var(--un-text-opacity));}
.prompt-header{display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;}
.btn{display:inline-flex;cursor:pointer;align-items:center;justify-content:center;gap:0.5rem;border-width:0px;border-radius:0.375rem;--un-bg-opacity:1;background-color:rgb(17 24 39 / var(--un-bg-opacity));padding-left:0.875rem;padding-right:0.875rem;padding-top:0.5rem;padding-bottom:0.5rem;font-size:0.95em;--un-text-opacity:1;color:rgb(255 255 255 / var(--un-text-opacity));transition-property:color,background-color,border-color,text-decoration-color,fill,stroke;transition-timing-function:cubic-bezier(0.4, 0, 0.2, 1);transition-duration:150ms;transition-duration:200ms;}
.prompt-inline-toggle{display:inline-flex;align-items:center;gap:0.375rem;font-size:0.9em;--un-text-opacity:1;color:rgb(55 65 81 / var(--un-text-opacity));}
.edit-drawer-open{--un-translate-x:0;transform:translateX(var(--un-translate-x)) translateY(var(--un-translate-y)) translateZ(var(--un-translate-z)) rotate(var(--un-rotate)) rotateX(var(--un-rotate-x)) rotateY(var(--un-rotate-y)) rotateZ(var(--un-rotate-z)) skewX(var(--un-skew-x)) skewY(var(--un-skew-y)) scaleX(var(--un-scale-x)) scaleY(var(--un-scale-y)) scaleZ(var(--un-scale-z));}
.prompt-summary:hover{--un-translate-y:-0.125rem;transform:translateX(var(--un-translate-x)) translateY(var(--un-translate-y)) translateZ(var(--un-translate-z)) rotate(var(--un-rotate)) rotateX(var(--un-rotate-x)) rotateY(var(--un-rotate-y)) rotateZ(var(--un-rotate-z)) skewX(var(--un-skew-x)) skewY(var(--un-skew-y)) scaleX(var(--un-scale-x)) scaleY(var(--un-scale-y)) scaleZ(var(--un-scale-z));--un-shadow:0 16px 32px var(--un-shadow-color, rgba(79, 70, 229, 0.12));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.drawer-close{cursor:pointer;border-width:0px;background-color:transparent;font-size:1.2em;--un-text-opacity:1;color:rgb(107 114 128 / var(--un-text-opacity));}
.prompt-message-content{white-space:pre-wrap;overflow-wrap:break-word;}
.prompt-context{border-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));border-radius:0.5rem;--un-bg-opacity:1;background-color:rgb(248 250 252 / var(--un-bg-opacity));padding:0.5rem;font-size:0.9em;}
.prompt-summary{border-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));border-radius:0.5rem;--un-gradient-from-position:0%;--un-gradient-from:rgb(248 250 252 / var(--un-from-opacity, 1)) var(--un-gradient-from-position);--un-gradient-to-position:100%;--un-gradient-to:rgb(248 250 252 / 0) var(--un-gradient-to-position);--un-gradient-stops:var(--un-gradient-from), var(--un-gradient-to);--un-gradient-to:rgb(224 231 255 / var(--un-to-opacity, 1)) var(--un-gradient-to-position);--un-gradient-shape:to bottom right in oklch;--un-gradient:var(--un-gradient-shape), var(--un-gradient-stops);background-image:linear-gradient(var(--un-gradient));padding:0.625rem;--un-shadow:0 12px 24px var(--un-shadow-color, rgba(79, 70, 229, 0.08));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);transition-property:transform;transition-timing-function:cubic-bezier(0.4, 0, 0.2, 1);transition-duration:150ms;transition-property:box-shadow;transition-duration:200ms;}
.prompt-system{border-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));border-radius:0.5rem;--un-bg-opacity:1;background-color:rgb(249 250 251 / var(--un-bg-opacity));padding:0.625rem;}
.edit-panel{border-bottom-width:1px;--un-border-opacity:1;border-color:rgb(229 231 235 / var(--un-border-opacity));--un-bg-opacity:1;background-color:rgb(255 247 237 / var(--un-bg-opacity));padding-left:1.25rem;padding-right:1.25rem;padding-top:0.875rem;padding-bottom:0.875rem;}
.edit-toggle-on{--un-border-opacity:1;border-color:rgb(249 115 22 / var(--un-border-opacity));--un-bg-opacity:1;background-color:rgb(249 115 22 / var(--un-bg-opacity));--un-text-opacity:1;color:rgb(17 24 39 / var(--un-text-opacity));}
.prompt-message-assistant{--un-border-opacity:1;border-color:rgb(167 243 208 / var(--un-border-opacity));--un-bg-opacity:1;background-color:rgb(236 253 245 / var(--un-bg-opacity));}
.prompt-message-user{--un-border-opacity:1;border-color:rgb(191 219 254 / var(--un-border-opacity));--un-bg-opacity:1;background-color:rgb(239 246 255 / var(--un-bg-opacity));}
.btn-secondary{--un-bg-opacity:1;background-color:rgb(249 115 22 / var(--un-bg-opacity));--un-text-opacity:1;color:rgb(17 24 39 / var(--un-text-opacity));}
.nav-link-active{--un-bg-opacity:1;background-color:rgb(59 130 246 / var(--un-bg-opacity));--un-text-opacity:1;color:rgb(255 255 255 / var(--un-text-opacity));font-weight:500;}
.btn:hover{--un-bg-opacity:1;background-color:rgb(31 41 55 / var(--un-bg-opacity));}
.btn-secondary:hover{--un-bg-opacity:1;background-color:rgb(251 146 60 / var(--un-bg-opacity));}
.edit-toggle:hover{--un-bg-opacity:1;background-color:rgb(31 41 55 / var(--un-bg-opacity));}
.edit-toggle-on:hover{--un-bg-opacity:1;background-color:rgb(251 146 60 / var(--un-bg-opacity));}
.nav-link:hover{--un-bg-opacity:1;background-color:rgb(229 231 235 / var(--un-bg-opacity));--un-text-opacity:1;color:rgb(17 24 39 / var(--un-text-opacity));}
.nav-link-up:hover{--un-bg-opacity:1;background-color:rgb(209 250 229 / var(--un-bg-opacity));--un-text-opacity:1;color:rgb(5 150 105 / var(--un-text-opacity));}
.prompt-summary-body{font-size:0.92em;--un-text-opacity:1;color:rgb(55 65 81 / var(--un-text-opacity));}
.small-note{font-size:0.75rem;line-height:1rem;--un-text-opacity:1;color:rgb(107 114 128 / var(--un-text-opacity));}
.edit-status-success{--un-text-opacity:1;color:rgb(21 128 61 / var(--un-text-opacity));}
.nav-link-up{--un-text-opacity:1;color:rgb(16 185 129 / var(--un-text-opacity));font-weight:500;}
/* layer: default */
.visible{visibility:visible;}
.absolute{position:absolute;}
.relative{position:relative;}
.static{position:static;}
.grid{display:grid;}
.inline{display:inline;}
.block{display:block;}
.contents{display:contents;}
.hidden{display:none;}
.flex{display:flex;}
.inline-flex{display:inline-flex;}
.flex-wrap{flex-wrap:wrap;}
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.border{border-width:1px;}
.rounded{border-radius:0.25rem;}
.uppercase{text-transform:uppercase;}
.underline{text-decoration-line:underline;}
.opacity-60{opacity:0.6;}
.backdrop-filter{-webkit-backdrop-filter:var(--un-backdrop-blur) var(--un-backdrop-brightness) var(--un-backdrop-contrast) var(--un-backdrop-grayscale) var(--un-backdrop-hue-rotate) var(--un-backdrop-invert) var(--un-backdrop-opacity) var(--un-backdrop-saturate) var(--un-backdrop-sepia);backdrop-filter:var(--un-backdrop-blur) var(--un-backdrop-brightness) var(--un-backdrop-contrast) var(--un-backdrop-grayscale) var(--un-backdrop-hue-rotate) var(--un-backdrop-invert) var(--un-backdrop-opacity) var(--un-backdrop-saturate) var(--un-backdrop-sepia);}

/* -------------- Reset & Layout -------------- */
body, html {
  margin: 0;
  padding: 0;
  height: 100%;
  font-family: "Inter", sans-serif;
  overflow: auto; /* Allow body scroll */
  background-color: #f0f2f5;
}

.container {
  display: flex;
  height: 100vh;
  position: relative;
}

/* -------------- Sidebar -------------- */
#appShell {
  --app-shell-sidebar-width: 318px;
  --app-shell-surface: linear-gradient(180deg, rgba(250, 252, 255, 0.98), rgba(240, 245, 255, 0.96));
  --app-shell-line: rgba(148, 163, 184, 0.28);
  --app-shell-shadow: 0 24px 48px rgba(15, 23, 42, 0.12);
  --app-shell-text: #334155;
  --app-shell-text-strong: #0f172a;
  position: relative;
  background: radial-gradient(circle at top left, rgba(96, 165, 250, 0.12), transparent 28%), linear-gradient(180deg, #f8fbff 0%, #eef4ff 52%, #e9eff8 100%);
}

#appSidebar {
  width: var(--app-shell-sidebar-width);
  min-width: var(--app-shell-sidebar-width);
  padding: 96px 16px 24px;
  border-right: 1px solid var(--app-shell-line);
  background: var(--app-shell-surface);
  box-shadow: var(--app-shell-shadow);
  backdrop-filter: blur(18px);
  overflow-y: auto;
  position: relative;
}

#appSidebar::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.7), rgba(255, 255, 255, 0) 18%), radial-gradient(circle at top left, rgba(59, 130, 246, 0.12), transparent 34%);
  pointer-events: none;
}

#appSidebar > * {
  position: relative;
  z-index: 1;
}

#appSidebar[hidden] {
  display: none !important;
}

.sidebar-toggle {
  position: absolute;
  left: calc(var(--app-shell-sidebar-width) - 26px);
  bottom: 18px;
  z-index: 12;
  width: 48px;
  height: 48px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 16px;
  background: linear-gradient(180deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.94));
  color: #ffffff;
  box-shadow: 0 18px 36px rgba(15, 23, 42, 0.24);
  cursor: pointer;
  transition: left 0.22s ease, transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.sidebar-toggle:hover {
  transform: translateY(-1px);
  box-shadow: 0 22px 42px rgba(15, 23, 42, 0.28);
}

#appShell.sidebar-collapsed .sidebar-toggle {
  left: 14px;
  border-color: rgba(148, 163, 184, 0.28);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96));
  color: #0f172a;
  box-shadow: 0 16px 32px rgba(15, 23, 42, 0.14);
}

.sidebar-toggle__icon {
  display: inline-grid;
  gap: 4px;
  width: 18px;
}

.sidebar-toggle__bar {
  display: block;
  width: 18px;
  height: 2px;
  border-radius: 999px;
  background: currentColor;
  transform-origin: center;
  transition: transform 0.18s ease, opacity 0.18s ease;
}

.sidebar-toggle[aria-expanded=true] .sidebar-toggle__bar:nth-child(1) {
  transform: translateY(6px) rotate(45deg);
}

.sidebar-toggle[aria-expanded=true] .sidebar-toggle__bar:nth-child(2) {
  opacity: 0;
}

.sidebar-toggle[aria-expanded=true] .sidebar-toggle__bar:nth-child(3) {
  transform: translateY(-6px) rotate(-45deg);
}

#appSidebar .app-edit-toggle-wrap {
  position: absolute;
  top: 18px;
  left: 16px;
  right: 16px;
  z-index: 2;
}

#appSidebar .edit-toggle {
  width: 100%;
  border: 0;
  border-radius: 18px;
  background: linear-gradient(135deg, #0f172a, #1e293b 58%, #334155);
  color: #ffffff;
  padding: 14px 18px;
  font-weight: 650;
  letter-spacing: 0.01em;
  box-shadow: 0 18px 34px rgba(15, 23, 42, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

#appSidebar .edit-toggle.on {
  background: linear-gradient(135deg, #fb923c, #f97316 56%, #fb7185);
  color: #111827;
}

#sidebarLoading {
  margin: 0 0 10px;
  padding: 12px 14px;
  justify-content: flex-start;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.74);
  box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.16);
  backdrop-filter: blur(8px);
}

#navList.nav-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 8px;
}

#navList.nav-list li {
  list-style: none;
  margin: 0;
  padding: 0;
}

#appSidebar .nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0;
  padding: 12px 14px;
  border-radius: 18px;
  text-decoration: none;
  color: var(--app-shell-text);
  font-weight: 560;
  background: rgba(255, 255, 255, 0.52);
  box-shadow: inset 0 0 0 1px transparent;
  transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, color 0.18s ease;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

#appSidebar .nav-link:hover {
  transform: translateX(3px);
  color: var(--app-shell-text-strong);
  background: rgba(255, 255, 255, 0.9);
  box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.14), 0 14px 24px rgba(148, 163, 184, 0.16);
}

#appSidebar .nav-link-up {
  color: #0f766e;
  background: linear-gradient(180deg, rgba(16, 185, 129, 0.08), rgba(13, 148, 136, 0.04));
}

#appSidebar .nav-link-active {
  color: #ffffff;
  background: linear-gradient(135deg, #60a5fa, #3b82f6 52%, #2563eb);
  box-shadow: 0 18px 28px rgba(37, 99, 235, 0.26), inset 0 1px 0 rgba(255, 255, 255, 0.16);
}

#appSidebar .item-icon {
  margin-right: 0;
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  padding: 6px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.78);
  box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.2);
}

#appSidebar .nav-link-active .item-icon {
  background: rgba(255, 255, 255, 0.16);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
}

#appSidebar .nav-link-up .item-icon {
  background: rgba(16, 185, 129, 0.12);
  box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.16);
}

@media (max-width: 720px) {
  #appShell {
    --app-shell-sidebar-width: 286px;
  }
  #appSidebar {
    padding: 88px 14px 24px;
  }
  .sidebar-toggle {
    bottom: 14px;
  }
  #appSidebar .app-edit-toggle-wrap {
    top: 14px;
    left: 14px;
    right: 14px;
  }
}
/* -------------- Main Content -------------- */
.main-content {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  height: 100vh;
  min-height: 100vh;
  background-color: #ffffff;
  position: relative;
  overflow-x: hidden;
  overflow-y: auto;
  min-width: 0;
}

.preview-shell {
  position: relative;
  --prompt-dock-reserve: 0px;
  flex: 0 0 auto;
  height: 100vh;
  min-height: 100vh;
  overflow: hidden;
  background: #ffffff;
  scroll-margin-top: 16px;
}

.prompt-dock {
  position: absolute;
  top: 20px;
  right: 20px;
  bottom: 20px;
  z-index: 2;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  align-items: stretch;
  width: min(620px, 100% - 40px);
  min-height: 0;
  pointer-events: none;
}

.prompt-dock:empty {
  display: none;
}

/* Iframe */
.content-frame {
  flex-grow: 1;
  border: none;
  height: 100%;
  min-height: 0;
  overflow: auto;
  padding-bottom: 24px;
}

.content-frame > .viewer {
  min-height: 100%;
  box-sizing: border-box;
  width: 100%;
}

.content-frame > .viewer .viewer-template--folder {
  padding-inline-end: calc(24px + var(--prompt-dock-reserve));
  transition: padding-inline-end 0.18s ease;
}

.content-frame > .viewer .poff-default-layout__main {
  padding-inline-end: calc(var(--poff-shell-main-padding) + var(--prompt-dock-reserve));
  transition: padding-inline-end 0.18s ease;
}

.content-frame[data-disabled=true] {
  position: relative;
}

.content-frame[data-disabled=true] a,
.content-frame[data-disabled=true] button,
.content-frame[data-disabled=true] input,
.content-frame[data-disabled=true] select,
.content-frame[data-disabled=true] textarea,
.content-frame[data-disabled=true] summary,
.content-frame[data-disabled=true] iframe,
.content-frame[data-disabled=true] video,
.content-frame[data-disabled=true] audio,
.content-frame[data-disabled=true] [contenteditable=true] {
  pointer-events: none;
}

.content-frame.content-frame-layout-target .poff-default-layout__main {
  position: relative;
  border: 1px dashed rgba(99, 102, 241, 0.42);
  border-radius: 18px;
  background: linear-gradient(180deg, rgba(99, 102, 241, 0.05), rgba(99, 102, 241, 0.02)), rgba(15, 23, 42, 0.02);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08), 0 0 0 10px rgba(99, 102, 241, 0.06);
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}

.content-frame.content-frame-layout-target[data-disabled=true] .poff-default-layout__main {
  opacity: 0.88;
}

.content-frame.content-frame-layout-target .poff-default-layout__main::before {
  content: "Layout Preview";
  display: inline-flex;
  align-items: center;
  margin-bottom: 14px;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(99, 102, 241, 0.12);
  color: #4338ca;
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.content-frame.content-frame-layout-target[data-disabled=true]::after {
  content: "Preview disabled";
  position: absolute;
  top: 12px;
  right: 14px;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.76);
  color: #cbd5e1;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  pointer-events: none;
}

@media (min-width: 1181px) {
  .preview-shell:has(.prompt-dock:not(:empty) .prompt-layer:not(.prompt-layer-collapsed)) {
    --prompt-dock-reserve: calc(clamp(320px, 38vw, 620px) + 40px);
  }
}
/* Edit mode */
/* Edit mode */
.edit-panel {
  position: relative;
  z-index: 1;
  border-bottom: 1px solid #e5e7eb;
  background: #fff7ed;
  padding: 14px 20px;
}

.edit-panel h3 {
  margin: 0 0 6px;
  font-size: 1.05em;
  color: #111827;
}

.edit-panel .edit-status {
  font-size: 0.9em;
  color: #b91c1c;
  margin-bottom: 8px;
}

.edit-panel .edit-status.success {
  color: #166534;
}

.edit-panel form {
  display: grid;
  gap: 12px;
}

.edit-panel .edit-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.edit-panel label {
  font-size: 0.85em;
  color: #374151;
  display: block;
  margin-bottom: 4px;
}

.edit-panel input[type=text],
.edit-panel textarea,
.edit-panel select,
.edit-drawer input[type=text],
.edit-drawer input[type=password],
.edit-drawer textarea,
.edit-drawer select {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 0.95em;
  box-sizing: border-box;
}

.edit-panel textarea {
  min-height: 72px;
  resize: vertical;
}

.edit-panel .edit-tree {
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 10px;
  background: #ffffff;
  max-height: 220px;
  overflow: auto;
}

.edit-panel .edit-tree-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  font-size: 0.9em;
  color: #374151;
}

.edit-panel .edit-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.edit-panel button {
  background: #111827;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 8px 14px;
  cursor: pointer;
  font-size: 0.95em;
}

.edit-panel button:hover {
  background: #1f2937;
}

.edit-panel .edit-inline {
  display: grid;
  gap: 12px;
}

.edit-panel .edit-inline-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.edit-panel .edit-layout-launch {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  margin-top: 12px;
  border: 1px solid #fed7aa;
  border-radius: 12px;
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
}

.edit-panel .edit-upload-launch {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  margin-top: 12px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
}

.edit-panel .edit-upload-launch-empty {
  border-color: #93c5fd;
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}

.edit-upload-dialog {
  width: min(560px, 100vw - 32px);
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 0;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
}

.edit-upload-dialog::backdrop {
  background: rgba(15, 23, 42, 0.35);
}

.edit-upload-dialog-form {
  display: grid;
  gap: 14px;
  padding: 16px;
  background: #ffffff;
}

.edit-layout-overlay {
  position: absolute;
  inset: 0;
  z-index: 5;
  background: rgba(15, 23, 42, 0.42);
  backdrop-filter: blur(2px);
  padding: 16px;
  overflow: auto;
}

.edit-layout-overlay-shell {
  width: min(1080px, 100% - 16px);
  min-height: calc(100% - 12px);
  margin-left: auto;
  border: 1px solid #cbd5e1;
  border-radius: 18px;
  background: linear-gradient(180deg, #fffaf2 0%, #ffffff 100%);
  box-shadow: 0 28px 80px rgba(15, 23, 42, 0.32);
  padding: 18px;
}

.edit-layout-overlay-form {
  display: grid;
  gap: 16px;
}

.edit-layout-overlay-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.edit-layout-meta {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.edit-layout-meta-card,
.edit-layout-editor {
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #ffffff;
  padding: 14px;
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
}

.edit-layout-meta-title {
  font-weight: 700;
  color: #111827;
  margin-bottom: 8px;
}

.edit-layout-meta-card .small-note,
.edit-layout-editor .small-note {
  display: block;
  margin-top: 4px;
}

.edit-layout-editor-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.edit-layout-editor textarea:disabled {
  background: #f8fafc;
  color: #64748b;
  cursor: not-allowed;
}

.edit-layout-panel {
  display: grid;
  gap: 16px;
}

.edit-layout-summary {
  align-items: flex-start;
  gap: 16px;
}

.edit-layout-summary-line {
  color: #6b7280;
  font-size: 0.95rem;
  line-height: 1.45;
}

.edit-layout-header-actions {
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.edit-layout-section-note {
  border: 1px solid #fed7aa;
  border-radius: 14px;
  background: #fff7ed;
  padding: 12px 14px;
}

.edit-layout-manual {
  margin-top: 18px;
}

.edit-layout-workspace {
  display: grid;
  gap: 16px;
}

.edit-layout-advanced {
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.74);
  padding: 10px 14px 14px;
}

.edit-layout-advanced-summary {
  cursor: pointer;
  font-weight: 700;
  color: #111827;
  margin-bottom: 10px;
}

.edit-panel .edit-layout-copy {
  display: grid;
  gap: 4px;
}

.edit-panel .edit-layout-title {
  font-weight: 700;
  color: #9a3412;
}

.edit-panel .edit-layout-launch .small-note {
  color: #7c2d12;
}

.edit-panel .edit-layout-launch button {
  white-space: nowrap;
}

.edit-panel .edit-secondary {
  background: #f97316;
}

.edit-panel .edit-secondary:hover {
  background: #ea580c;
}

.edit-drawer {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 360px;
  background: #ffffff;
  border-left: 1px solid #e5e7eb;
  box-shadow: -8px 0 18px rgba(15, 23, 42, 0.08);
  padding: 16px;
  overflow: auto;
  transform: translateX(100%);
  transition: transform 0.2s ease;
  z-index: 30;
}

.edit-drawer.open,
.edit-drawer.edit-drawer-open {
  transform: translateX(0%);
}

.edit-drawer h4 {
  margin: 0 0 8px;
  font-size: 1em;
  color: #111827;
}

.edit-drawer .drawer-close {
  border: none;
  background: transparent;
  font-size: 1.2em;
  cursor: pointer;
  color: #6b7280;
}

.edit-drawer .drawer-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.prompt-window {
  display: grid;
  gap: 12px;
  margin-top: 10px;
  padding-top: 6px;
  border-top: 1px dashed #e5e7eb;
}

.prompt-inline {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px;
  margin-top: 16px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  border-top: none;
  padding-top: 12px;
}

.prompt-dock .prompt-window {
  pointer-events: auto;
  min-height: 0;
}

.prompt-layer {
  position: relative;
  width: 100%;
  min-height: 0;
  display: flex;
  justify-content: flex-start;
  align-items: flex-start;
  pointer-events: auto;
}

.prompt-layer-toggle {
  position: absolute;
  right: 12px;
  bottom: 12px;
  z-index: 3;
  width: 48px;
  height: 48px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(148, 163, 184, 0.24);
  border-radius: 16px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96));
  color: #0f172a;
  box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.prompt-layer-toggle:hover {
  transform: translateY(-1px);
  box-shadow: 0 22px 42px rgba(15, 23, 42, 0.16);
}

.prompt-layer-toggle-open {
  font-size: 0.82rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  transform: rotate(180deg);
}

.prompt-layer-toggle[hidden] {
  display: none !important;
}

.prompt-dock .prompt-inline {
  width: 100%;
  max-height: 100%;
  min-height: 0;
  margin-top: 0;
  overflow: auto;
  overscroll-behavior: contain;
  padding-bottom: 72px;
  border: 1px solid rgba(203, 213, 225, 0.52);
  background: linear-gradient(180deg, rgba(226, 232, 240, 0.78) 0%, rgba(203, 213, 225, 0.72) 100%);
  box-shadow: 0 26px 70px rgba(15, 23, 42, 0.24);
  backdrop-filter: blur(3px);
}

.prompt-layer-collapsed {
  min-height: 100%;
}

.prompt-layer-collapsed .prompt-window {
  display: none;
}

.prompt-dock .prompt-context {
  max-height: 180px;
  overflow: auto;
}

.prompt-section-messages .prompt-messages,
.prompt-section-context .prompt-context {
  margin-top: 8px;
}

.prompt-dock .prompt-messages {
  max-height: 220px;
}

.prompt-dock .prompt-template-code {
  min-height: 120px;
  max-height: 220px;
}

.prompt-dock .prompt-textarea {
  max-height: 220px;
}

.prompt-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.prompt-grid {
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
}

.prompt-section {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 8px 10px;
  background: #ffffff;
}

.prompt-section summary {
  cursor: pointer;
  font-weight: 700;
  color: #111827;
  margin: -4px -4px 6px;
  padding: 4px;
}

.prompt-section[open] summary {
  color: #0f172a;
}

.prompt-messages {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px;
  background: #f8fafc;
  max-height: 160px;
  overflow: auto;
  font-size: 0.9em;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

.prompt-message {
  margin: 0 0 8px;
  padding: 6px 8px;
  border-radius: 6px;
  background: #fff;
  border: 1px solid #e5e7eb;
}

.prompt-message .role {
  font-weight: 600;
  margin-right: 6px;
}

.prompt-message.user {
  background: #eff6ff;
  border-color: #dbeafe;
}

.prompt-message.assistant {
  background: #ecfdf3;
  border-color: #bbf7d0;
}

.prompt-message .content {
  white-space: pre-wrap;
  word-break: break-word;
}

.prompt-summary {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px 12px;
  background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
  box-shadow: 0 12px 24px rgba(79, 70, 229, 0.08);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.prompt-summary:hover {
  transform: translateY(-2px);
  box-shadow: 0 16px 32px rgba(79, 70, 229, 0.12);
}

.prompt-summary-generating {
  border-color: #fdba74;
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
  box-shadow: 0 16px 34px rgba(249, 115, 22, 0.18);
}

.prompt-summary-title {
  font-weight: 700;
  color: #111827;
  margin-bottom: 6px;
}

.prompt-summary-body {
  color: #374151;
  font-size: 0.92em;
}

.prompt-allowed {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9em;
  color: #065f46;
  background: #ecfdf3;
  border: 1px solid #bbf7d0;
  border-radius: 10px;
  padding: 8px 10px;
}

.prompt-allowed .dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #16a34a;
  box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.15);
}

.prompt-generation {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #fdba74;
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
  color: #9a3412;
  box-shadow: 0 10px 24px rgba(249, 115, 22, 0.14);
}

.prompt-generation[hidden] {
  display: none !important;
}

.prompt-generation-pulse {
  width: 12px;
  height: 12px;
  border-radius: 999px;
  background: #f97316;
  box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.45);
  animation: prompt-pulse 1.1s ease-out infinite;
}

.prompt-generation-label {
  font-weight: 700;
  letter-spacing: 0.01em;
}

.stream-cursor {
  display: inline-block;
  width: 8px;
  height: 14px;
  background: #9ca3af;
  margin-left: 2px;
  animation: blink 1s steps(1) infinite;
}

@keyframes blink {
  50% {
    opacity: 0;
  }
}
@keyframes prompt-pulse {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 10px rgba(249, 115, 22, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(249, 115, 22, 0);
  }
}
.prompt-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}

.prompt-actions-left {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.prompt-attachment {
  display: grid;
  grid-template-columns: 96px minmax(0, 1fr) auto;
  gap: 12px;
  align-items: center;
  padding: 10px 12px;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  background: #eff6ff;
}

.prompt-attachment[hidden] {
  display: none !important;
}

.prompt-attachment-preview-wrap {
  width: 96px;
  height: 72px;
  border-radius: 8px;
  overflow: hidden;
  background: #dbeafe;
  display: flex;
  align-items: center;
  justify-content: center;
}

.prompt-attachment-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.prompt-attachment-meta {
  min-width: 0;
}

.prompt-attachment-name {
  font-weight: 600;
  color: #1e3a8a;
  word-break: break-word;
}

.prompt-input-has-attachment {
  border-color: #93c5fd;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14);
}

.prompt-window-generating .prompt-messages {
  border-color: #fdba74;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.45), 0 0 0 3px rgba(251, 146, 60, 0.12);
}

.prompt-window-generating #prompt-send {
  background: #ea580c;
  box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.18);
}

.prompt-window-generating #prompt-send:disabled,
.prompt-window-generating #prompt-attach:disabled,
.prompt-window-generating #prompt-attachment-remove:disabled,
.prompt-window-generating #prompt-clear:disabled,
.prompt-window-generating #prompt-input:disabled {
  cursor: wait;
}

.prompt-dock .prompt-section,
.prompt-dock .prompt-template-viewer,
.prompt-dock .prompt-system,
.prompt-dock .prompt-summary,
.prompt-dock .prompt-attachment {
  background: rgba(255, 255, 255, 0.86);
  border-color: rgba(255, 255, 255, 0.34);
  box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
  backdrop-filter: blur(10px);
}

.prompt-dock .prompt-messages,
.prompt-dock .prompt-context,
.prompt-dock .prompt-template-code,
.prompt-dock .prompt-input,
.prompt-dock .prompt-system textarea,
.prompt-dock .form-input {
  background: rgba(255, 255, 255, 0.9);
  border-color: rgba(203, 213, 225, 0.9);
  backdrop-filter: blur(8px);
}

.prompt-dock .prompt-input::placeholder,
.prompt-dock .prompt-system textarea::placeholder,
.prompt-dock .form-input::placeholder {
  color: #64748b;
}

.prompt-settings-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: -6px;
}

.prompt-context {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
  background: #f8fafc;
  font-size: 0.9em;
}

.prompt-context-grid {
  display: grid;
  gap: 10px;
}

.prompt-context-item {
  display: grid;
  gap: 6px;
  padding: 8px 10px;
  border: 1px solid #dbe3ee;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.9);
}

.prompt-context-key {
  font-weight: 700;
  color: #0f172a;
}

.prompt-context-value {
  min-width: 0;
}

.prompt-context-code {
  display: block;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  word-break: break-word;
  font-size: 0.88em;
  line-height: 1.45;
  color: #334155;
}

.prompt-context-list {
  display: grid;
  gap: 8px;
}

.prompt-context-list--nested {
  margin-top: 4px;
}

.prompt-context-list-item {
  padding: 8px 10px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.prompt-context-object {
  display: grid;
  gap: 8px;
}

.prompt-context-object--nested {
  margin-top: 4px;
}

.prompt-context-object-row {
  display: grid;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.prompt-context-object-key {
  font-size: 0.74rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #475569;
}

.prompt-context-object-value {
  min-width: 0;
}

.prompt-context-title {
  font-weight: 600;
  margin-bottom: 4px;
  color: #111827;
}

.prompt-context-row {
  color: #374151;
  margin: 2px 0;
  word-break: break-word;
}

.prompt-template-viewer {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #ffffff;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
  padding: 8px 10px 10px;
}

.prompt-template-viewer-summary {
  cursor: pointer;
  font-weight: 700;
  color: #111827;
}

.prompt-template-viewer-body {
  display: grid;
  gap: 8px;
  margin-top: 8px;
}

.prompt-template-viewer-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
}

.prompt-template-code {
  min-height: 180px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size: 0.88em;
  line-height: 1.45;
  white-space: pre;
  overflow: auto;
  background: #f8fafc;
}

.prompt-inline-toggle {
  display: inline-flex;
  gap: 6px;
  align-items: center;
  font-size: 0.9em;
  color: #374151;
}

.prompt-inline-toggle label,
.prompt-inline-toggle input[type=checkbox] {
  cursor: pointer;
}

.prompt-inline-toggle input {
  margin: 0;
}

.prompt-system {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 6px 10px 10px;
  background: #f9fafb;
}

.prompt-system[open] {
  padding-bottom: 12px;
}

.prompt-system summary {
  cursor: pointer;
  font-weight: 600;
  margin-bottom: 6px;
}

.prompt-system textarea {
  margin-top: 6px;
  min-height: 90px;
}

.prompt-system-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: 6px;
  flex-wrap: wrap;
}

.small-note {
  font-size: 0.8em;
  color: #6b7280;
}

.prompt-dock .edit-panel-title,
.prompt-dock .prompt-section summary,
.prompt-dock .prompt-template-viewer-summary,
.prompt-dock .prompt-summary-title,
.prompt-dock .prompt-context-title,
.prompt-dock .prompt-generation-label,
.prompt-dock .prompt-attachment-name {
  color: #0f172a;
}

.prompt-dock .small-note,
.prompt-dock .prompt-summary-body,
.prompt-dock .prompt-context-row,
.prompt-dock .prompt-context-code,
.prompt-dock .prompt-context-object-key,
.prompt-dock .prompt-context-value,
.prompt-dock .prompt-context-object-value,
.prompt-dock .prompt-inline-toggle,
.prompt-dock .prompt-message,
.prompt-dock .prompt-message .content {
  color: #334155;
}

.prompt-dock .prompt-message.user,
.prompt-dock .prompt-message.assistant {
  color: #1f2937;
}

@media (max-width: 640px) {
  .edit-layout-overlay {
    padding: 12px;
  }
  .edit-layout-overlay-shell {
    width: 100%;
    min-height: auto;
    padding: 14px;
  }
  .edit-layout-editor-head {
    flex-direction: column;
  }
  .prompt-attachment {
    grid-template-columns: 1fr;
  }
  .prompt-attachment-preview-wrap {
    width: 100%;
    height: 160px;
  }
  .prompt-template-viewer-head {
    align-items: stretch;
  }
  .prompt-dock {
    top: 12px;
    right: 12px;
    bottom: 12px;
    width: calc(100% - 24px);
    max-height: calc(100% - 24px);
  }
}
/* POFF_STYLE_END */
    </style>
</head>
<body>
