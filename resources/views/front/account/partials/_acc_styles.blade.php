<style>
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap');

:root {
    --bg:       #060608;
    --surface:  #0e0e12;
    --surface2: #16161d;
    --border:   rgba(255,255,255,0.07);
    --gold:     #f5b800;
    --gold-d:   #c99300;
    --text:     #e8e8ef;
    --muted:    #6b6b7e;
    --red:      #e8445a;
    --green:    #22c55e;
    --radius:   14px;
    --fh:       'Syne', sans-serif;
    --fb:       'DM Sans', sans-serif;
}

/* ── Base ── */
.acc-page { background: var(--bg); min-height: calc(100vh - 200px); padding: 36px 0 80px; font-family: var(--fb); color: var(--text); }

/* ── Banner ── */
.acc-banner {
    background: linear-gradient(135deg,#060608 0%,#0e0e12 60%,#0d0b00 100%);
    border-bottom: 1px solid rgba(245,184,0,.12);
    padding: 44px 0 32px; position: relative; overflow: hidden;
}
.acc-banner::before {
    content:''; position:absolute; top:-60px; right:-60px;
    width:280px; height:280px;
    background: radial-gradient(circle,rgba(245,184,0,.07) 0%,transparent 70%);
    pointer-events:none;
}
.acc-banner-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; }
.acc-banner-left {}
.acc-banner-label { font-family:var(--fh); font-size:10px; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:var(--gold); margin-bottom:8px; display:flex; align-items:center; gap:8px; }
.acc-banner-label::before { content:''; width:20px; height:2px; background:var(--gold); border-radius:2px; }
.acc-banner-title { font-family:var(--fh); font-size:26px; font-weight:800; color:#fff; letter-spacing:-.5px; margin:0; }
.acc-banner-title span { color:var(--gold); }
.acc-banner-sub { margin:4px 0 0; color:var(--muted); font-size:13px; }

/* ── Navigation ── */
.acc-nav {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:12px;
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius); padding:14px 20px;
    margin-bottom:28px;
}
.acc-nav-links { display:flex; flex-wrap:wrap; gap:8px; }
.acc-nav-link {
    display:inline-flex; align-items:center; gap:7px;
    font-family:var(--fh); font-size:11px; font-weight:700;
    letter-spacing:.5px; text-transform:uppercase;
    padding:8px 16px; border-radius:8px;
    border:1px solid var(--border); color:var(--muted);
    text-decoration:none; transition:all .18s;
}
.acc-nav-link i { font-size:12px; }
.acc-nav-link:hover { color:var(--gold); border-color:rgba(245,184,0,.3); background:rgba(245,184,0,.05); text-decoration:none; }
.acc-nav-link.active { background:var(--gold); border-color:var(--gold); color:#000; }
.acc-nav-link.active:hover { color:#000; }
.acc-nav-logout {
    display:inline-flex; align-items:center; gap:7px;
    font-family:var(--fh); font-size:11px; font-weight:700;
    letter-spacing:.5px; text-transform:uppercase;
    padding:8px 16px; border-radius:8px;
    border:1px solid rgba(232,68,90,.25); color:var(--red);
    background:transparent; cursor:pointer; transition:all .18s;
}
.acc-nav-logout:hover { background:rgba(232,68,90,.1); border-color:rgba(232,68,90,.5); }

/* ── Card ── */
.acc-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:24px; }
.acc-card-head { display:flex; align-items:center; justify-content:space-between; padding:16px 22px; border-bottom:1px solid var(--border); background:var(--surface2); }
.acc-card-title { font-family:var(--fh); font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:var(--gold); display:flex; align-items:center; gap:8px; }
.acc-card-title::before { content:''; width:3px; height:12px; background:var(--gold); border-radius:2px; }

/* ── Stat cards ── */
.acc-stats { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:28px; }
@media(max-width:480px){ .acc-stats { grid-template-columns:1fr; } }
.acc-stat {
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
    padding:22px 24px; position:relative; overflow:hidden;
    transition:border-color .2s,transform .2s; text-decoration:none; display:block;
}
.acc-stat:hover { border-color:rgba(245,184,0,.3); transform:translateY(-2px); text-decoration:none; }
.acc-stat::after { content:''; position:absolute; top:0; right:0; width:80px; height:80px; background:radial-gradient(circle at top right,rgba(245,184,0,.06),transparent 70%); pointer-events:none; }
.acc-stat-icon { width:38px; height:38px; border-radius:10px; background:rgba(245,184,0,.1); border:1px solid rgba(245,184,0,.2); color:var(--gold); display:flex; align-items:center; justify-content:center; font-size:15px; margin-bottom:14px; }
.acc-stat-num { font-family:var(--fh); font-size:36px; font-weight:800; color:#fff; line-height:1; margin-bottom:4px; }
.acc-stat-label { font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.8px; font-weight:600; }
.acc-stat-link { display:inline-flex; align-items:center; gap:5px; font-size:12px; color:var(--gold); margin-top:12px; font-weight:600; }

/* ── Table ── */
.acc-table { width:100%; border-collapse:collapse; }
.acc-table th { font-family:var(--fh); font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:var(--muted); padding:12px 22px; text-align:left; border-bottom:1px solid var(--border); }
.acc-table td { padding:14px 22px; font-size:13.5px; color:var(--text); border-bottom:1px solid rgba(255,255,255,.04); vertical-align:middle; }
.acc-table tr:last-child td { border-bottom:none; }
.acc-table tbody tr { transition:background .15s; }
.acc-table tbody tr:hover { background:rgba(255,255,255,.02); }
.acc-mono { font-family:monospace; font-size:12px; color:var(--gold); }

/* ── Badges ── */
.acc-badge { display:inline-flex; align-items:center; gap:5px; font-size:10px; font-weight:700; letter-spacing:.5px; padding:3px 10px; border-radius:99px; white-space:nowrap; font-family:var(--fh); }
.acc-badge-pending  { color:var(--gold);  background:rgba(245,184,0,.10); border:1px solid rgba(245,184,0,.25); }
.acc-badge-paid     { color:var(--green); background:rgba(34,197,94,.10); border:1px solid rgba(34,197,94,.25); }
.acc-badge-rejected { color:var(--red);   background:rgba(232,68,90,.10); border:1px solid rgba(232,68,90,.25); }
.acc-badge-default  { color:var(--muted); background:rgba(107,107,126,.1); border:1px solid rgba(107,107,126,.2); }

/* ── Buttons ── */
.acc-btn { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:6px 13px; border-radius:7px; background:rgba(245,184,0,.08); border:1px solid rgba(245,184,0,.22); color:var(--gold); text-decoration:none; transition:all .18s; font-family:var(--fh); }
.acc-btn:hover { background:rgba(245,184,0,.16); border-color:rgba(245,184,0,.45); color:var(--gold); text-decoration:none; }
.acc-submit { display:inline-flex; align-items:center; gap:8px; background:var(--gold); color:#000; font-family:var(--fh); font-size:13px; font-weight:800; letter-spacing:.5px; text-transform:uppercase; border:none; border-radius:8px; padding:13px 28px; cursor:pointer; transition:background .2s,transform .1s; }
.acc-submit:hover { background:#ffc820; }
.acc-submit:active { transform:scale(.99); }

/* ── Form ── */
.acc-field { margin-bottom:18px; }
.acc-field label { display:block; font-size:11px; font-weight:500; letter-spacing:.8px; text-transform:uppercase; color:var(--muted); margin-bottom:7px; }
.acc-field input,.acc-field select,.acc-field textarea { width:100%; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-family:var(--fb); font-size:14px; padding:11px 14px; outline:none; transition:border-color .2s,box-shadow .2s; box-sizing:border-box; }
.acc-field input::placeholder { color:#3a3a4a; }
.acc-field input:focus,.acc-field select:focus,.acc-field textarea:focus { border-color:var(--gold-d); box-shadow:0 0 0 3px rgba(245,184,0,.1); }
.acc-field input.is-invalid { border-color:var(--red); }
.acc-field .acc-error { font-size:12px; color:var(--red); margin-top:5px; }
.acc-file-label { display:flex; align-items:center; gap:10px; background:var(--surface2); border:1px dashed rgba(245,184,0,.3); border-radius:8px; padding:12px 16px; cursor:pointer; font-size:13px; color:var(--muted); transition:border-color .2s; }
.acc-file-label:hover { border-color:rgba(245,184,0,.6); color:var(--text); }
.acc-file-label i { color:var(--gold); font-size:16px; }
.acc-file-label input { display:none; }
.acc-hint { font-size:11px; color:var(--muted); margin-top:5px; }

/* ── Alert ── */
.acc-alert-success { background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.25); border-radius:8px; padding:13px 18px; margin-bottom:20px; font-size:13px; color:var(--green); display:flex; align-items:center; gap:10px; }

/* ── Empty ── */
.acc-empty { text-align:center; padding:40px 20px; }
.acc-empty i { font-size:30px; color:rgba(245,184,0,.12); display:block; margin-bottom:10px; }
.acc-empty span { font-size:13px; color:var(--muted); }

/* ── Pagination ── */
.acc-page-footer { padding:14px 22px; border-top:1px solid var(--border); background:var(--surface2); }
.acc-page-footer .pagination { margin:0; gap:5px; display:flex; flex-wrap:wrap; }
.acc-page-footer .pagination li a,.acc-page-footer .pagination li span { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:34px; padding:0 10px; background:var(--surface); border:1px solid var(--border); border-radius:7px; font-family:var(--fh); font-size:11px; font-weight:700; color:var(--muted); text-decoration:none; transition:all .18s; }
.acc-page-footer .pagination li.active span,.acc-page-footer .pagination li a:hover { background:var(--gold); border-color:var(--gold); color:#000; }
.acc-page-footer .pagination li.disabled span { opacity:.3; }

/* ── Avatar ── */
.acc-avatar-wrap { text-align:center; margin-bottom:24px; }
.acc-avatar { width:90px; height:90px; border-radius:50%; border:2px solid rgba(245,184,0,.3); object-fit:cover; display:block; margin:0 auto 10px; }
.acc-avatar-initials { width:90px; height:90px; border-radius:50%; background:rgba(245,184,0,.1); border:2px solid rgba(245,184,0,.25); display:flex; align-items:center; justify-content:center; font-family:var(--fh); font-size:30px; font-weight:800; color:var(--gold); margin:0 auto 10px; }
.acc-avatar-name { font-family:var(--fh); font-size:16px; font-weight:700; color:#fff; }
.acc-avatar-username { font-size:12px; color:var(--muted); margin-top:2px; }

/* ── Grid helpers ── */
.acc-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.acc-grid-13 { display:grid; grid-template-columns:1fr 3fr; gap:24px; align-items:start; }
@media(max-width:768px){ .acc-grid-13 { grid-template-columns:1fr; } }
@media(max-width:640px){ .acc-grid-2 { grid-template-columns:1fr; } }

/* ── Dashboard tables grid ── */
.acc-tables-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
@media(max-width:768px){ .acc-tables-grid { grid-template-columns:1fr; } }
</style>
