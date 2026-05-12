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
.stream-cursor{margin-left:0.125rem;display:inline-block;width:0.5rem;height:0.875rem;animation:pulse 2s cubic-bezier(0.4,0,.6,1) infinite;--un-bg-opacity:1;background-color:rgb(156 163 175 / var(--un-bg-opacity));}
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
.sticky{position:sticky;}
.static{position:static;}
.top-0{top:0;}
.z-10{z-index:10;}
.z-20{z-index:20;}
.grid{display:grid;}
.col-span-2{grid-column:span 2/span 2;}
.col-span-3{grid-column:span 3/span 3;}
.col-span-9{grid-column:span 9/span 9;}
.grid-cols-1{grid-template-columns:repeat(1,minmax(0,1fr));}
.grid-cols-12{grid-template-columns:repeat(12,minmax(0,1fr));}
.grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr));}
.grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr));}
.grid-cols-4{grid-template-columns:repeat(4,minmax(0,1fr));}
.m-0{margin:0;}
.mx-auto{margin-left:auto;margin-right:auto;}
.mb-2{margin-bottom:0.5rem;}
.mb-3{margin-bottom:0.75rem;}
.mt-1{margin-top:0.25rem;}
.mt-2{margin-top:0.5rem;}
.mt-3{margin-top:0.75rem;}
.mt-3\.5{margin-top:0.875rem;}
.inline{display:inline;}
.block{display:block;}
.inline-block{display:inline-block;}
.contents{display:contents;}
.hidden{display:none;}
.h-screen{height:100vh;}
.h1{height:0.25rem;}
.max-h-screen{max-height:100vh;}
.max-w-full{max-width:100%;}
.min-h-0{min-height:0;}
.min-h-96{min-height:24rem;}
.min-h-full{min-height:100%;}
.min-h-screen{min-height:100vh;}
.w-full{width:100%;}
.max-w-screen-lg{max-width:1024px;}
.max-w-screen-xl{max-width:1280px;}
.flex{display:flex;}
.inline-flex{display:inline-flex;}
.flex-1{flex:1 1 0%;}
.flex-col{flex-direction:column;}
.flex-wrap{flex-wrap:wrap;}
@keyframes pulse{0%, 100% {opacity:1} 50% {opacity:.5}}
.animate-pulse{animation:pulse 2s cubic-bezier(0.4,0,.6,1) infinite;}
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.items-start{align-items:flex-start;}
.items-center{align-items:center;}
.justify-center{justify-content:center;}
.justify-between{justify-content:space-between;}
.gap-1{gap:0.25rem;}
.gap-1\.5{gap:0.375rem;}
.gap-2{gap:0.5rem;}
.gap-3{gap:0.75rem;}
.gap-4{gap:1rem;}
.gap-6{gap:1.5rem;}
.overflow-auto{overflow:auto;}
.overflow-hidden{overflow:hidden;}
.overflow-x-hidden{overflow-x:hidden;}
.overflow-y-auto{overflow-y:auto;}
.whitespace-pre-wrap{white-space:pre-wrap;}
.break-words{overflow-wrap:break-word;}
.border{border-width:1px;}
.border-0{border-width:0px;}
.border-b{border-bottom-width:1px;}
.border-r{border-right-width:1px;}
.border-amber-500{--un-border-opacity:1;border-color:rgb(245 158 11 / var(--un-border-opacity));}
.border-cyan-500{--un-border-opacity:1;border-color:rgb(6 182 212 / var(--un-border-opacity));}
.border-green-500{--un-border-opacity:1;border-color:rgb(34 197 94 / var(--un-border-opacity));}
.border-pink-500{--un-border-opacity:1;border-color:rgb(236 72 153 / var(--un-border-opacity));}
.border-red-200{--un-border-opacity:1;border-color:rgb(254 202 202 / var(--un-border-opacity));}
.border-red-500{--un-border-opacity:1;border-color:rgb(239 68 68 / var(--un-border-opacity));}
.border-slate-200{--un-border-opacity:1;border-color:rgb(226 232 240 / var(--un-border-opacity));}
.border-slate-300{--un-border-opacity:1;border-color:rgb(203 213 225 / var(--un-border-opacity));}
.border-slate-700{--un-border-opacity:1;border-color:rgb(51 65 85 / var(--un-border-opacity));}
.border-slate-800{--un-border-opacity:1;border-color:rgb(30 41 59 / var(--un-border-opacity));}
.border-transparent{border-color:transparent;}
.border-white\/10{border-color:rgb(255 255 255 / 0.1);}
.border-yellow-400{--un-border-opacity:1;border-color:rgb(250 204 21 / var(--un-border-opacity));}
.hover\:border-blue-400:hover{--un-border-opacity:1;border-color:rgb(96 165 250 / var(--un-border-opacity));}
.rounded{border-radius:0.25rem;}
.rounded-2xl{border-radius:1rem;}
.rounded-full{border-radius:9999px;}
.rounded-lg{border-radius:0.5rem;}
.rounded-md{border-radius:0.375rem;}
.rounded-xl{border-radius:0.75rem;}
.bg-amber-500{--un-bg-opacity:1;background-color:rgb(245 158 11 / var(--un-bg-opacity));}
.bg-amber-500\/20{background-color:rgb(245 158 11 / 0.2);}
.bg-blue-50{--un-bg-opacity:1;background-color:rgb(239 246 255 / var(--un-bg-opacity));}
.bg-blue-500{--un-bg-opacity:1;background-color:rgb(59 130 246 / var(--un-bg-opacity));}
.bg-blue-500\/20{background-color:rgb(59 130 246 / 0.2);}
.bg-cyan-500\/20{background-color:rgb(6 182 212 / 0.2);}
.bg-emerald-500\/20{background-color:rgb(16 185 129 / 0.2);}
.bg-green-500{--un-bg-opacity:1;background-color:rgb(34 197 94 / var(--un-bg-opacity));}
.bg-green-500\/20{background-color:rgb(34 197 94 / 0.2);}
.bg-neutral-950{--un-bg-opacity:1;background-color:rgb(10 10 10 / var(--un-bg-opacity));}
.bg-pink-500\/20{background-color:rgb(236 72 153 / 0.2);}
.bg-red-50{--un-bg-opacity:1;background-color:rgb(254 242 242 / var(--un-bg-opacity));}
.bg-red-500{--un-bg-opacity:1;background-color:rgb(239 68 68 / var(--un-bg-opacity));}
.bg-red-500\/20{background-color:rgb(239 68 68 / 0.2);}
.bg-slate-100{--un-bg-opacity:1;background-color:rgb(241 245 249 / var(--un-bg-opacity));}
.bg-slate-300{--un-bg-opacity:1;background-color:rgb(203 213 225 / var(--un-bg-opacity));}
.bg-slate-50{--un-bg-opacity:1;background-color:rgb(248 250 252 / var(--un-bg-opacity));}
.bg-slate-900{--un-bg-opacity:1;background-color:rgb(15 23 42 / var(--un-bg-opacity));}
.bg-slate-900\/70{background-color:rgb(15 23 42 / 0.7);}
.bg-slate-950{--un-bg-opacity:1;background-color:rgb(2 6 23 / var(--un-bg-opacity));}
.bg-white{--un-bg-opacity:1;background-color:rgb(255 255 255 / var(--un-bg-opacity));}
.bg-white\/90{background-color:rgb(255 255 255 / 0.9);}
.bg-yellow-400{--un-bg-opacity:1;background-color:rgb(250 204 21 / var(--un-bg-opacity));}
.bg-yellow-400\/20{background-color:rgb(250 204 21 / 0.2);}
.hover\:bg-amber-500:hover{--un-bg-opacity:1;background-color:rgb(245 158 11 / var(--un-bg-opacity));}
.hover\:bg-blue-600:hover{--un-bg-opacity:1;background-color:rgb(37 99 235 / var(--un-bg-opacity));}
.hover\:bg-green-600:hover{--un-bg-opacity:1;background-color:rgb(22 163 74 / var(--un-bg-opacity));}
.hover\:bg-red-100:hover{--un-bg-opacity:1;background-color:rgb(254 226 226 / var(--un-bg-opacity));}
.hover\:bg-red-600:hover{--un-bg-opacity:1;background-color:rgb(220 38 38 / var(--un-bg-opacity));}
.hover\:bg-slate-100:hover{--un-bg-opacity:1;background-color:rgb(241 245 249 / var(--un-bg-opacity));}
.hover\:bg-slate-50:hover{--un-bg-opacity:1;background-color:rgb(248 250 252 / var(--un-bg-opacity));}
.from-blue-500{--un-gradient-from-position:0%;--un-gradient-from:rgb(59 130 246 / var(--un-from-opacity, 1)) var(--un-gradient-from-position);--un-gradient-to-position:100%;--un-gradient-to:rgb(59 130 246 / 0) var(--un-gradient-to-position);--un-gradient-stops:var(--un-gradient-from), var(--un-gradient-to);}
.from-slate-950{--un-gradient-from-position:0%;--un-gradient-from:rgb(2 6 23 / var(--un-from-opacity, 1)) var(--un-gradient-from-position);--un-gradient-to-position:100%;--un-gradient-to:rgb(2 6 23 / 0) var(--un-gradient-to-position);--un-gradient-stops:var(--un-gradient-from), var(--un-gradient-to);}
.from-white{--un-gradient-from-position:0%;--un-gradient-from:rgb(255 255 255 / var(--un-from-opacity, 1)) var(--un-gradient-from-position);--un-gradient-to-position:100%;--un-gradient-to:rgb(255 255 255 / 0) var(--un-gradient-to-position);--un-gradient-stops:var(--un-gradient-from), var(--un-gradient-to);}
.via-slate-900{--un-gradient-via-position:50%;--un-gradient-to:rgb(15 23 42 / 0);--un-gradient-stops:var(--un-gradient-from), rgb(15 23 42 / var(--un-via-opacity, 1)) var(--un-gradient-via-position), var(--un-gradient-to);}
.to-blue-600{--un-gradient-to-position:100%;--un-gradient-to:rgb(37 99 235 / var(--un-to-opacity, 1)) var(--un-gradient-to-position);}
.to-indigo-950{--un-gradient-to-position:100%;--un-gradient-to:rgb(30 27 75 / var(--un-to-opacity, 1)) var(--un-gradient-to-position);}
.to-slate-100{--un-gradient-to-position:100%;--un-gradient-to:rgb(241 245 249 / var(--un-to-opacity, 1)) var(--un-gradient-to-position);}
.to-slate-900{--un-gradient-to-position:100%;--un-gradient-to:rgb(15 23 42 / var(--un-to-opacity, 1)) var(--un-gradient-to-position);}
.to-slate-950{--un-gradient-to-position:100%;--un-gradient-to:rgb(2 6 23 / var(--un-to-opacity, 1)) var(--un-gradient-to-position);}
.bg-gradient-to-b{--un-gradient-shape:to bottom in oklch;--un-gradient:var(--un-gradient-shape), var(--un-gradient-stops);background-image:linear-gradient(var(--un-gradient));}
.bg-gradient-to-br{--un-gradient-shape:to bottom right in oklch;--un-gradient:var(--un-gradient-shape), var(--un-gradient-stops);background-image:linear-gradient(var(--un-gradient));}
.bg-gradient-to-r{--un-gradient-shape:to right in oklch;--un-gradient:var(--un-gradient-shape), var(--un-gradient-stops);background-image:linear-gradient(var(--un-gradient));}
.object-cover{object-fit:cover;}
.object-contain{object-fit:contain;}
.p-0{padding:0;}
.p-2{padding:0.5rem;}
.p-3{padding:0.75rem;}
.p-4{padding:1rem;}
.p-6{padding:1.5rem;}
.px-2\.5{padding-left:0.625rem;padding-right:0.625rem;}
.px-3{padding-left:0.75rem;padding-right:0.75rem;}
.px-4{padding-left:1rem;padding-right:1rem;}
.px-6{padding-left:1.5rem;padding-right:1.5rem;}
.py-1{padding-top:0.25rem;padding-bottom:0.25rem;}
.py-2{padding-top:0.5rem;padding-bottom:0.5rem;}
.py-3{padding-top:0.75rem;padding-bottom:0.75rem;}
.py-4{padding-top:1rem;padding-bottom:1rem;}
.text-2xl{font-size:1.5rem;line-height:2rem;}
.text-3xl{font-size:1.875rem;line-height:2.25rem;}
.text-base{font-size:1rem;line-height:1.5rem;}
.text-lg{font-size:1.125rem;line-height:1.75rem;}
.text-sm{font-size:0.875rem;line-height:1.25rem;}
.text-xs{font-size:0.75rem;line-height:1rem;}
.text-amber-500{--un-text-opacity:1;color:rgb(245 158 11 / var(--un-text-opacity));}
.text-blue-100{--un-text-opacity:1;color:rgb(219 234 254 / var(--un-text-opacity));}
.text-blue-300{--un-text-opacity:1;color:rgb(147 197 253 / var(--un-text-opacity));}
.text-blue-700{--un-text-opacity:1;color:rgb(29 78 216 / var(--un-text-opacity));}
.text-cyan-500{--un-text-opacity:1;color:rgb(6 182 212 / var(--un-text-opacity));}
.text-emerald-300{--un-text-opacity:1;color:rgb(110 231 183 / var(--un-text-opacity));}
.text-emerald-500{--un-text-opacity:1;color:rgb(16 185 129 / var(--un-text-opacity));}
.text-green-500{--un-text-opacity:1;color:rgb(34 197 94 / var(--un-text-opacity));}
.text-indigo-200{--un-text-opacity:1;color:rgb(199 210 254 / var(--un-text-opacity));}
.text-pink-500{--un-text-opacity:1;color:rgb(236 72 153 / var(--un-text-opacity));}
.text-red-500{--un-text-opacity:1;color:rgb(239 68 68 / var(--un-text-opacity));}
.text-red-600{--un-text-opacity:1;color:rgb(220 38 38 / var(--un-text-opacity));}
.text-red-700{--un-text-opacity:1;color:rgb(185 28 28 / var(--un-text-opacity));}
.text-slate-100{--un-text-opacity:1;color:rgb(241 245 249 / var(--un-text-opacity));}
.text-slate-200{--un-text-opacity:1;color:rgb(226 232 240 / var(--un-text-opacity));}
.text-slate-300{--un-text-opacity:1;color:rgb(203 213 225 / var(--un-text-opacity));}
.text-slate-400{--un-text-opacity:1;color:rgb(148 163 184 / var(--un-text-opacity));}
.text-slate-50{--un-text-opacity:1;color:rgb(248 250 252 / var(--un-text-opacity));}
.text-slate-500{--un-text-opacity:1;color:rgb(100 116 139 / var(--un-text-opacity));}
.text-slate-700{--un-text-opacity:1;color:rgb(51 65 85 / var(--un-text-opacity));}
.text-slate-800{--un-text-opacity:1;color:rgb(30 41 59 / var(--un-text-opacity));}
.text-slate-900{--un-text-opacity:1;color:rgb(15 23 42 / var(--un-text-opacity));}
.text-white{--un-text-opacity:1;color:rgb(255 255 255 / var(--un-text-opacity));}
.text-yellow-500{--un-text-opacity:1;color:rgb(234 179 8 / var(--un-text-opacity));}
.text-inherit{color:inherit;}
.font-bold{font-weight:700;}
.font-medium{font-weight:500;}
.font-semibold{font-weight:600;}
.leading-tight{line-height:1.25;}
.tracking-wide{letter-spacing:0.025em;}
.tracking-wider{letter-spacing:0.05em;}
.uppercase{text-transform:uppercase;}
.hover\:underline:hover{text-decoration-line:underline;}
.focus-visible\:underline:focus-visible{text-decoration-line:underline;}
.no-underline{text-decoration:none;}
.opacity-60{opacity:0.6;}
.shadow{--un-shadow:var(--un-shadow-inset) 0 1px 3px 0 var(--un-shadow-color, rgb(0 0 0 / 0.1)),var(--un-shadow-inset) 0 1px 2px -1px var(--un-shadow-color, rgb(0 0 0 / 0.1));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.shadow-2xl{--un-shadow:var(--un-shadow-inset) 0 25px 50px -12px var(--un-shadow-color, rgb(0 0 0 / 0.25));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.shadow-lg{--un-shadow:var(--un-shadow-inset) 0 10px 15px -3px var(--un-shadow-color, rgb(0 0 0 / 0.1)),var(--un-shadow-inset) 0 4px 6px -4px var(--un-shadow-color, rgb(0 0 0 / 0.1));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.shadow-xl{--un-shadow:var(--un-shadow-inset) 0 20px 25px -5px var(--un-shadow-color, rgb(0 0 0 / 0.1)),var(--un-shadow-inset) 0 8px 10px -6px var(--un-shadow-color, rgb(0 0 0 / 0.1));box-shadow:var(--un-ring-offset-shadow), var(--un-ring-shadow), var(--un-shadow);}
.transition{transition-property:color,background-color,border-color,text-decoration-color,fill,stroke,opacity,box-shadow,transform,filter,backdrop-filter;transition-timing-function:cubic-bezier(0.4, 0, 0.2, 1);transition-duration:150ms;}
@media (min-width: 768px){
.md\:grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr));}
.md\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr));}
.md\:w-auto{width:auto;}
.md\:p-4{padding:1rem;}
.md\:p-6{padding:1.5rem;}
.md\:px-4{padding-left:1rem;padding-right:1rem;}
}
@media (min-width: 1024px){
.lg\:col-span-3{grid-column:span 3/span 3;}
.lg\:col-span-9{grid-column:span 9/span 9;}
.lg\:grid-cols-12{grid-template-columns:repeat(12,minmax(0,1fr));}
.lg\:h-screen{height:100vh;}
}
@media (min-width: 1280px){
.xl\:col-span-2{grid-column:span 2/span 2;}
.xl\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr));}
}

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
  isolation: isolate;
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
  position: relative;
  z-index: 0;
  flex-grow: 1;
  border: none;
  height: 100%;
  min-height: 0;
  overflow: auto;
  padding-bottom: 24px;
  transform: translateZ(0);
  contain: paint;
}

.content-frame > .viewer {
  min-height: 100%;
  box-sizing: border-box;
  width: 100%;
}

.content-frame > .viewer .poff-default-layout__main {
  box-sizing: border-box;
  padding-inline-end: calc(var(--poff-shell-main-padding-inline) + var(--prompt-dock-reserve));
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
  opacity: 0.96;
}

.content-frame.content-frame-layout-target[data-disabled=true] .poff-default-layout__main > * {
  display: none !important;
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

.content-frame.content-frame-layout-target[data-disabled=true] .poff-default-layout__main::after {
  content: "Prompt edits layout wrapper only.\aroot.title is the outer shell title.\awork.title is the inner item title.\aSee src/docs/prompt-layout-root-work-vars.md.";
  display: block;
  margin-top: 12px;
  padding: 18px 16px 14px;
  border: 1px dashed rgba(148, 163, 184, 0.62);
  border-radius: 14px;
  background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='96' viewBox='0 0 220 96'%3E%3Crect x='1' y='1' width='218' height='94' rx='10' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-dasharray='8 6'/%3E%3Crect x='16' y='16' width='188' height='12' rx='6' fill='%23cbd5e1'/%3E%3Crect x='16' y='38' width='140' height='9' rx='4.5' fill='%23e2e8f0'/%3E%3Crect x='16' y='55' width='168' height='9' rx='4.5' fill='%23e2e8f0'/%3E%3Crect x='16' y='72' width='112' height='9' rx='4.5' fill='%23e2e8f0'/%3E%3C/svg%3E") center 14px/min(340px, 100%) 118px no-repeat, linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.95));
  color: #334155;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  line-height: 1.42;
  white-space: pre-line;
  text-align: left;
  padding-top: 146px;
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

#appSidebar .edit-toggle.edit-toggle-on {
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

.edit-panel .edit-status.edit-status-success {
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

.edit-panel .edit-work-fields {
  display: grid;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid #dbeafe;
  border-radius: 16px;
  background: linear-gradient(135deg, #fbfdff 0%, #eef6ff 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72), 0 12px 30px rgba(15, 23, 42, 0.03);
}

.edit-panel .edit-work-fields-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.edit-panel .edit-work-fields-title {
  font-weight: 700;
  color: #111827;
  font-size: 1.02rem;
}

.edit-panel .edit-work-fields-list {
  display: grid;
  gap: 14px;
}

.edit-panel .edit-work-config-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.edit-panel .edit-work-config-field {
  display: grid;
  gap: 6px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #ffffff;
}

.edit-panel .edit-work-config-field .edit-label {
  margin-bottom: 0;
}

.edit-panel .edit-work-category-section {
  display: grid;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #c7d2fe;
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(238, 242, 255, 0.94));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.76), 0 12px 30px rgba(15, 23, 42, 0.03);
}

.edit-panel .edit-work-category-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.edit-panel .edit-work-category-controls {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

.edit-panel .edit-work-category-picker,
.edit-panel .edit-work-category-current {
  display: grid;
  gap: 8px;
}

.edit-panel .edit-work-category-picker-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.edit-panel .edit-work-category-picker-row .form-select {
  flex: 1 1 auto;
}

.edit-panel .edit-work-category-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  min-height: 42px;
}

.edit-panel .edit-work-category-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border: 1px solid #c4b5fd;
  border-radius: 999px;
  background: rgba(238, 242, 255, 0.98);
  color: #4338ca;
  font-size: 0.9rem;
  line-height: 1;
  cursor: pointer;
}

.edit-panel .edit-work-category-pill:hover {
  background: #e0e7ff;
}

.edit-panel .edit-work-category-pill span[aria-hidden=true] {
  font-size: 1.05em;
  font-weight: 700;
  line-height: 1;
}

.edit-drawer .edit-fieldset {
  display: grid;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid #dbeafe;
  border-radius: 16px;
  background: linear-gradient(135deg, #fbfdff 0%, #eef6ff 100%);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72), 0 12px 30px rgba(15, 23, 42, 0.03);
}

.edit-drawer .edit-fieldset-title {
  font-weight: 700;
  color: #111827;
  font-size: 1rem;
}

.edit-drawer .edit-template-map-list {
  display: grid;
  gap: 12px;
}

.edit-drawer .edit-template-map-row {
  display: grid;
  gap: 6px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #ffffff;
}

.edit-drawer .edit-template-map-row .edit-label {
  margin-bottom: 0;
  color: #111827;
  font-size: 0.82rem;
  font-weight: 700;
}

.edit-drawer .edit-template-map-row .small-note {
  margin-top: 0;
}

.edit-panel .edit-work-field-row {
  display: grid;
  gap: 12px;
  padding: 14px;
  border: 1px solid #cbd5e1;
  border-radius: 16px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.98));
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
}

.edit-panel .edit-work-field-main {
  display: grid;
  gap: 14px;
}

.edit-panel .edit-work-field-head {
  display: grid;
  gap: 12px;
  grid-template-columns: minmax(120px, 150px) minmax(200px, 1fr) auto;
  align-items: end;
}

.edit-panel .edit-work-field-name-wrap,
.edit-panel .edit-work-field-value-wrap {
  min-width: 0;
}

.edit-panel .edit-work-field-value-row {
  display: grid;
  gap: 12px;
}

.edit-panel .edit-work-field-value-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  min-height: 42px;
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: #ffffff;
}

.edit-panel .edit-work-field-value-toggle input {
  width: auto;
  margin: 0;
}

.edit-panel .edit-work-field-row .small-note {
  margin-top: 4px;
}

.edit-panel .edit-work-field-remove {
  min-width: 42px;
  padding: 8px 10px;
  line-height: 1;
  align-self: end;
}

.edit-panel .edit-work-field-advanced {
  border-top: 1px dashed #dbeafe;
  padding-top: 12px;
}

.edit-panel .edit-work-field-advanced summary {
  cursor: pointer;
  font-weight: 600;
  color: #334155;
  margin-bottom: 12px;
  list-style: none;
}

.edit-panel .edit-work-field-advanced-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}

.edit-panel .edit-work-field-schema-group {
  display: grid;
  gap: 10px;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: rgba(248, 250, 252, 0.88);
}

.edit-panel .edit-work-field-schema-group-title {
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #475569;
}

.edit-panel .edit-work-field-schema-group-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.edit-panel .edit-work-field-small-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  grid-column: 1/-1;
}

.edit-panel .edit-work-field-bools {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  padding-top: 4px;
  align-items: center;
}

.edit-panel .edit-work-field-check {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.9em;
  color: #334155;
  margin: 0;
}

.edit-panel .edit-work-field-check input {
  width: auto;
  margin: 0;
}

.edit-panel .edit-work-fields-empty {
  padding: 2px 2px 0;
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

.edit-layout-select-actions {
  margin-top: 10px;
  justify-content: flex-end;
}

.edit-layout-manual {
  margin-top: 18px;
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

@media (max-width: 720px) {
  .edit-layout-select-actions {
    justify-content: stretch;
  }
  .edit-layout-select-actions .btn {
    width: 100%;
  }
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
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 13;
  width: auto;
  min-height: 0;
  display: inline-flex;
  justify-content: flex-end;
  align-items: flex-end;
  pointer-events: none;
}

.prompt-layer-collapsed .prompt-window {
  display: none;
}

.prompt-layer-collapsed .prompt-layer-toggle-open {
  position: static;
  right: auto;
  bottom: auto;
  pointer-events: auto;
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

.prompt-message-role {
  font-weight: 600;
  margin-right: 6px;
}

.prompt-message.prompt-message-user {
  background: #eff6ff;
  border-color: #dbeafe;
}

.prompt-message.prompt-message-assistant {
  background: #ecfdf3;
  border-color: #bbf7d0;
}

.prompt-message-content {
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

.prompt-allowed .prompt-dot {
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

.prompt-context-item--accent {
  border-color: #93c5fd;
  background: linear-gradient(180deg, rgba(239, 246, 255, 0.95), rgba(255, 255, 255, 0.96));
  box-shadow: 0 8px 20px rgba(59, 130, 246, 0.08);
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
.prompt-dock .prompt-message-content {
  color: #334155;
}

.prompt-dock .prompt-message-user,
.prompt-dock .prompt-message-assistant {
  color: #1f2937;
}

@media (max-width: 640px) {
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
  .edit-panel .edit-work-field-row {
    grid-template-columns: 1fr;
  }
  .edit-panel .edit-work-field-head {
    grid-template-columns: 1fr;
  }
  .edit-panel .edit-work-field-schema-group-grid,
  .edit-panel .edit-work-field-small-grid {
    grid-template-columns: 1fr;
  }
  .prompt-dock {
    top: 12px;
    right: 12px;
    bottom: 12px;
    width: calc(100% - 24px);
    max-height: calc(100% - 24px);
  }
  .prompt-layer-collapsed {
    right: 14px;
    bottom: 14px;
  }
}
/* POFF_STYLE_END */
    </style>
</head>
<body>
