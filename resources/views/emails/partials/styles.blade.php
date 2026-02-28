{{-- resources/views/emails/partials/styles.blade.php
     @include this in every HTML email blade inside <head>
--}}
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body, html { width: 100% !important; background: #09090c !important;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
  -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { border: 0; display: block; }
a { color: #f5b800; }

.eb  { background: #09090c; padding: 40px 16px; }
.ew  { max-width: 580px; margin: 0 auto; background: #0e0e13;
       border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; overflow: hidden; }

.eh  { padding: 22px 32px; border-bottom: 1px solid rgba(255,255,255,0.07); text-align: center; }
.logo .g { color: #f5b800; font-size: 26px; font-weight: 800; letter-spacing: -1px; }
.logo .w { color: #fff;    font-size: 26px; font-weight: 800; letter-spacing: -1px; }

.ehero      { padding: 30px 32px 24px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: center; }
.ehero-icon { font-size: 44px; display: block; margin-bottom: 14px; }
.ehero h1   { font-size: 22px; font-weight: 700; color: #fff; letter-spacing: -0.3px; margin-bottom: 8px; line-height: 1.3; }
.ehero p    { font-size: 14px; color: #8080a0; line-height: 1.65; }

.ebody { padding: 28px 32px; }
.ep    { font-size: 14px; color: #b8b8cc; line-height: 1.75; margin-bottom: 14px; }
.ep strong, .ep b { color: #fff; font-weight: 600; }
.ep-sm { font-size: 12px; color: #5e5e78; line-height: 1.6; }

.einfo { background: #15151e; border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; margin: 20px 0; overflow: hidden; }
.einfo-row { display: flex; justify-content: space-between; align-items: center; padding: 11px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 13px; gap: 12px; }
.einfo-row:last-child { border-bottom: none; }
.einfo-label { color: #505068; font-size: 11px; text-transform: uppercase; letter-spacing: 0.6px; flex-shrink: 0; }
.einfo-val   { color: #dddde8; font-weight: 500; text-align: right; }
.einfo-val.gold  { color: #f5b800; font-weight: 700; }
.einfo-val.green { color: #22c55e; }
.einfo-val.red   { color: #e8445a; }

.ecta-wrap { text-align: center; margin: 26px 0 10px; }
.ecta      { display: inline-block; background: #f5b800; color: #000 !important; font-size: 15px; font-weight: 700; text-decoration: none; padding: 14px 38px; border-radius: 9px; letter-spacing: 0.2px; }
.ecta-sub  { text-align: center; font-size: 12px; color: #505068; margin-top: 8px; }

.eurl { background: #15151e; border: 1px dashed rgba(245,184,0,0.2); border-radius: 8px; padding: 11px 16px; margin: 14px 0; word-break: break-all; font-size: 12px; color: #505068; text-align: center; }
.eurl a { color: #f5b800; text-decoration: none; }

.ealert       { border-radius: 8px; padding: 13px 16px; font-size: 13px; line-height: 1.55; margin: 18px 0; }
.ealert.gold  { background: rgba(245,184,0,0.07);  border: 1px solid rgba(245,184,0,0.2);  color: #c99300; }
.ealert.green { background: rgba(34,197,94,0.07);  border: 1px solid rgba(34,197,94,0.2);  color: #86efac; }
.ealert.red   { background: rgba(232,68,90,0.07);  border: 1px solid rgba(232,68,90,0.2);  color: #fca5a5; }
.ealert.blue  { background: rgba(59,130,246,0.07); border: 1px solid rgba(59,130,246,0.2); color: #93c5fd; }

.ediv { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 22px 0; }

.eticket { background: #15151e; border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 15px 18px; margin-bottom: 10px; }
.eticket:last-child { margin-bottom: 0; }
.eticket-num  { font-size: 12px; font-weight: 700; color: #f5b800; margin-bottom: 3px; }
.eticket-name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 6px; }
.eticket-meta { font-size: 12px; color: #505068; margin-bottom: 10px; line-height: 1.5; }
.eticket-links { display: flex; gap: 8px; flex-wrap: wrap; }
.eticket-links a { font-size: 12px; font-weight: 600; color: #f5b800 !important; text-decoration: none; padding: 6px 14px; border-radius: 7px; border: 1px solid rgba(245,184,0,0.25); background: rgba(245,184,0,0.07); }

.efooter   { background: #09090c; border-top: 1px solid rgba(255,255,255,0.06); padding: 22px 32px; text-align: center; }
.efooter p { font-size: 12px; color: #34344a; line-height: 1.7; }
.efooter a { color: #505068; text-decoration: none; }

@media (max-width: 600px) {
  .ew { border-radius: 0 !important; border-left: 0 !important; border-right: 0 !important; }
  .ebody, .ehero, .eh, .efooter { padding-left: 20px !important; padding-right: 20px !important; }
  .einfo-row { flex-direction: column; align-items: flex-start; gap: 3px; }
  .einfo-val { text-align: left !important; }
  .eticket-links a { font-size: 11px; padding: 5px 10px; }
}
</style>
