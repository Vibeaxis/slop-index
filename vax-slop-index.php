<?php
/*
Plugin Name: VAX Slop Index (No-iframe)
Description: Native widget (no iframes). Paste text or fetch a URL; scores "slop" (template-y AI/SEO sludge) using repetition, burstiness, lexical diversity, clichés, hedges, passive voice, buzzwords, paragraph rhythm, and simplicity. Hardened fetch (cache, rate-limit, SSRF guard).
Shortcode: [vax_slop_index]
Version: 1.1.2
Author: VibeAxis (Jason Bann) + GPT-5 Thinking
License: MIT
*/

if (!defined('ABSPATH')) exit;

class VAX_Slop_Index {
  const HANDLE = 'vax-sli';
  private static $needs_assets = false;

  public static function init() {
    add_shortcode('vax_slop_index', [__CLASS__, 'shortcode']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue']);
    add_action('wp_ajax_vax_sli_fetch', [__CLASS__, 'ajax_fetch']);
    add_action('wp_ajax_nopriv_vax_sli_fetch', [__CLASS__, 'ajax_fetch']);
  }

  public static function shortcode() {
    self::$needs_assets = true;
    ob_start(); ?>
    <div id="vax-sli" class="vax-sli">
      <div class="vax-sli__card">
        <h2 class="vax-sli__h2">VAX Slop Index</h2>
        <p class="vax-sli__sub">High score = more slop. Paste text or fetch a URL.</p>
        <div class="vax-sli__row">
          <input id="sli_url" type="url" placeholder="https://example.com/article" class="vax-sli__input" />
          <button class="vax-sli__btn" id="sli_fetch">Fetch</button>
          <button class="vax-sli__btn vax-sli__btn--ghost" id="sli_sample">Load sample</button>
          <label class="vax-sli__lbl"><input id="sli_norm" type="checkbox" checked /> normalize text</label>
          <span class="vax-sli__muted" id="sli_status" aria-live="polite"></span>
        </div>
      </div>

      <div class="vax-sli__card">
        <textarea id="sli_text" class="vax-sli__ta" placeholder="Paste text here or use Fetch"></textarea>
        <div class="vax-sli__row" style="margin-top:8px">
          <button class="vax-sli__btn" id="sli_analyze">Analyze</button>
          <button class="vax-sli__btn vax-sli__btn--ghost" id="sli_clear">Clear</button>
        </div>
      </div>

      <div class="vax-sli__grid">
        <div class="vax-sli__card vax-sli__c6">
          <h3>Score</h3>
          <div class="vax-sli__score" id="sli_score">—</div>
          <div class="vax-sli__muted">Range 0–100 · higher = sloppier</div>
          <div class="vax-sli__meter"><i id="sli_bar" style="width:0%"></i></div>

          <div class="vax-sli__summary" id="sli_summary" style="margin-top:10px"></div>
          <div class="vax-sli__row" style="justify-content:flex-end;margin-top:6px">
            <button class="vax-sli__btn vax-sli__btn--ghost" id="sli_copy">Copy Summary</button>
          </div>

          <div style="margin-top:8px" id="sli_highlights"></div>
        </div>

        <div class="vax-sli__card vax-sli__c6">
          <h3>Signals</h3>
          <div id="sli_signals"></div>
        </div>

        <details class="vax-sli__card vax-sli__c12" id="sli_details">
          <summary>Details & Export</summary>
          <div class="vax-sli__row" style="margin:6px 0">
            <button class="vax-sli__btn vax-sli__btn--ghost" id="sli_json">Export JSON</button>
          </div>
          <pre id="sli_detections">—</pre>
        </details>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }

  public static function maybe_enqueue() {
    if (!self::$needs_assets) return;

    // STYLE
    wp_register_style(self::HANDLE, false, [], '1.1.2');
    wp_enqueue_style(self::HANDLE);
    $css = <<<'CSS'
.vax-sli{max-width:1100px;margin:24px auto;padding:0 12px}
.vax-sli__card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;box-shadow:0 1px 0 rgba(15,23,42,.04);margin:16px 0}
.vax-sli__h2{font-size:24px;margin:0 0 8px}
.vax-sli__sub{color:#334155;font-size:14px;margin:0 0 10px}
.vax-sli__row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.vax-sli__input{width:420px;max-width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px}
.vax-sli__ta{width:100%;min-height:220px;padding:12px;border:1px solid #e2e8f0;border-radius:12px}
.vax-sli__btn{appearance:none;border:1px solid #0f172a;background:#0f172a;color:#fff;padding:10px 12px;border-radius:10px;cursor:pointer}
.vax-sli__btn--ghost{background:#fff;color:#0f172a}
.vax-sli__lbl{font-size:12px}
.vax-sli__muted{color:#64748b}
.vax-sli__grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
.vax-sli__c6{grid-column:span 6}
.vax-sli__c12{grid-column:span 12}
@media(max-width:1000px){.vax-sli__c6{grid-column:span 12}}
pre{background:#0b1220;color:#e2e8f0;border-radius:12px;padding:12px;margin:0;white-space:pre-wrap;word-break:break-word;max-height:38vh;overflow:auto}
.vax-sli__meter{height:10px;border-radius:999px;background:#eef2f7;position:relative;margin-top:8px}
.vax-sli__meter>i{position:absolute;left:0;top:0;bottom:0;border-radius:999px;background:linear-gradient(90deg,#ef4444,#f59e0b,#16a34a)}
.vax-sli__score{font-size:42px;font-weight:800;letter-spacing:-.02em}
.vax-sli .tag{display:inline-block;background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;border-radius:999px;padding:2px 6px;font-size:11px;margin:2px}
#sli_details>summary{cursor:pointer;user-select:none}
.vax-sli__summary{font-size:14px;line-height:1.5;color:#0f172a;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px}
/* inline help for signals */
.vax-sli .sig{margin:6px 0}
.vax-sli .sig .hdr{display:flex;justify-content:space-between;align-items:center}
.vax-sli .sig .lab{display:inline-flex;align-items:center;gap:6px}
.vax-sli .hint{
  display:inline-flex;align-items:center;justify-content:center;
  width:16px;height:16px;border-radius:999px;border:1px solid #94a3b8;
  font-size:11px;line-height:1;color:#334155;background:#fff;cursor:help;
}
.vax-sli .hint[data-tip]{position:relative}
.vax-sli .hint[data-tip]:hover::after{
  content:attr(data-tip); position:absolute; z-index:10; left:50%; transform:translateX(-50%);
  bottom:140%; white-space:normal; max-width:260px; padding:8px 10px; border-radius:8px;
  background:#0b1220; color:#e2e8f0; border:1px solid #1f2937; box-shadow:0 8px 24px rgba(2,6,23,.4);
}
.vax-sli .hint[data-tip]:hover::before{
  content:""; position:absolute; left:50%; transform:translateX(-50%); bottom:118%;
  border:6px solid transparent; border-top-color:#0b1220;
}
/* bigger, legible tooltips */
.vax-sli .hint[data-tip]:hover::after,
.vax-sli .hint[data-tip]:focus-visible::after,
.vax-sli .hint.open[data-tip]::after{
  font-size:12.5px; line-height:1.35;
  padding:10px 12px; min-width:220px; max-width:320px;
  opacity:1; visibility:visible;
}
.vax-sli .hint[data-tip]:hover::after{
  opacity:0; visibility:hidden; transition:opacity .12s ease, visibility .12s ease;
}
.vax-sli .hint{ background:#fff; color:#334155; } /* keep contrast on dark footers */
.vax-sli .hint.open::before,
.vax-sli .hint:focus-visible::before{ border-top-color:#0b1220; }

CSS;
    wp_add_inline_style(self::HANDLE, $css);

    // SCRIPT
    wp_register_script(self::HANDLE, false, [], '1.1.2', true);
    wp_enqueue_script(self::HANDLE);
    wp_localize_script(self::HANDLE, 'VAX_SLI', [
      'ajax'  => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('vax_sli_nonce'),
    ]);

$js = <<<'JS'
(function(){
  // ---------- tiny helpers ----------
  const $ = id => document.getElementById(id);
  const normOn = () => { const n=$('sli_norm'); return !!(n && n.checked); };
  const norm = s => {
    if(!normOn()) return (s||'');
    try { return (s||'').toLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g,''); }
    catch(e){ return (s||'').toLowerCase(); }
  };
  const escRe  = s => String(s).replace(/[.*+?^${}()|[\\]\\\\]/g,'\\\\$&');
  const plain  = s => norm(s).replace(/['"’\\-]+/g,' ').replace(/\\s+/g,' ').trim();
  const sentences = t => ((t||'').replace(/\\s+/g,' ').match(/[^.!?]+[.!?]?/g))||[];
  const words = t => (norm(t).match(/[a-z0-9'’]+/g)||[]);
  const uniq = a => Array.from(new Set(a));
  const ngrams = (arr,n) => { const out={}; for(let i=0;i<=arr.length-n;i++){ const g=arr.slice(i,i+n).join(' '); out[g]=(out[g]||0)+1; } return out; };
  const fkGrade = t => { const s=sentences(t).length||1; const w=words(t).length||1;
    const syll=words(t).reduce((a,w)=>{ const m=w.match(/[aeiouy]+/g); return a+Math.max(1,(m||[]).length); },0);
    return 0.39*(w/s)+11.8*(syll/w)-15.59; };
  const passiveHeu = t => { const m=norm(t).match(/\\b(was|were|is|are|be|been|being)\\b\\s+\\w+ed\\b\\s*(by\\b)?/g); return m?m.length:0; };

  const CLICHES=["in today's fast paced world","unlock the power","harness the power","game changer","cutting edge","seamless experience","leverage","empower","drive value","best in class","next level","paradigm shift","synergy","holistic","at the end of the day","mission critical","robust and scalable","ultimate guide","comprehensive guide","definitive guide"];
  const HEDGES=["might","may","could","somewhat","kind of","sort of","likely","possibly","arguably","perhaps"];
  const BUZZ=["seamless","robust","scalable","innovative","frictionless","intuitive","streamlined","optimize","transform","solution","platform","ecosystem"];

  // precompiled matchers (faster + fewer loops)
  const HEDGES_RE = new RegExp("(^|\\\\W)(?:" + HEDGES.map(escRe).join("|") + ")(\\\\W|$)","g");
  const BUZZ_RE   = new RegExp("(^|\\\\W)(?:" + BUZZ.map(escRe).join("|")   + ")(\\\\W|$)","g");

  // tooltip copy
  const SIG_INFO = {
    "Repetition": "Share of the single most common bigram vs all bigrams. High = looping phrasing.",
    "Burstiness": "Variation in sentence length (std/mean). Too smooth = templated; some swing = human.",
    "Low diversity": "Inverse type–token ratio. High = small vocabulary recycled a lot.",
    "Clichés/100w": "Boilerplate phrases per 100 words (e.g., “unlock the power”).",
    "Hedges/100w": "“might/may/could/sort of/likely…” per 100 words. Excess = covering tracks.",
    "Passive/sentence": "Passive-voice heuristic hits per sentence (“was Xed by”).",
    "Buzzwords/100w": "Marketing fog per 100 words (“robust, scalable, ecosystem…”).",
    "Uniformity": "Paragraph word-count sameness. Too even = factory extrusion.",
    "Too-simple grade": "Flesch–Kincaid: penalizes super-simple prose when other signals fire."
  };

  // ---------- analyzer ----------
  function analyze(text){
    const raw=text||''; const t=raw.trim();
    const sents=sentences(t); const ws=words(t);
    const wc=ws.length; const sc=sents.length;
    if(wc<20) return {ok:false,error:'Not enough text (min ~20 words).'};

    // bigrams + repetition
    const big=ngrams(ws,2);
    const bigArr=Object.entries(big).sort((a,b)=>b[1]-a[1]);
    const topBig=bigArr.slice(0,5);
    const totalBig=(Object.values(big).reduce((a,b)=>a+b,0))||1;
    const rep=(topBig.length?topBig[0][1]:0)/totalBig;

    // burstiness
    const lens=sents.map(s=>words(s).length||0);
    const mean=lens.reduce((a,b)=>a+b,0)/(lens.length||1);
    const variance=lens.reduce((a,b)=>a+Math.pow(b-mean,2),0)/(lens.length||1);
    const std=Math.sqrt(variance);
    const burst=Math.min(1, Math.max(0,(std/(mean||1))-0.3));

    // diversity (TTR)
    const types=uniq(ws).length;
    const ttr=types/(wc||1);
    const invDiversity=Math.max(0, 1 - ttr*3);

    // clichés (phrase list, safe escaped)
    const tp=plain(t);
    const cHits=[]; 
    for(let i=0;i<CLICHES.length;i++){
      const ph = CLICHES[i];
      const re = new RegExp("(?:^|\\W)"+escRe(ph)+"(?:\\W|$)","g");
      const m = tp.match(re);
      if(m) cHits.push([ph,m.length]);
    }
    const cRate=cHits.reduce((a,b)=>a+b[1],0)/Math.max(1,wc/100);

    // hedges + buzz (single regex each)
    const hRate = ((tp.match(HEDGES_RE)||[]).length) / Math.max(1,wc/100);
    const buzzRate = ((tp.match(BUZZ_RE)||[]).length) / Math.max(1,wc/100);

    // passive
    const passCount=passiveHeu(t);
    const passRate=passCount/Math.max(1,sc);

    // paragraph rhythm (word-count CV)
    const rawParas=t.split(/\\n\\s*\\n/).map(p=>p.trim()).filter(Boolean);
    const pWords=rawParas
      .filter(p=>!/^>/.test(p) && !/^#{1,6}\\s/.test(p))
      .map(p=>(plain(p).match(/[a-z0-9'’]+/g)||[]).length)
      .filter(n=>n>=12);
    let uniformity=0;
    if(pWords.length>=5 && wc>=300){
      const avg=pWords.reduce((a,b)=>a+b,0)/pWords.length;
      const varp=pWords.reduce((a,b)=>a+Math.pow(b-avg,2),0)/pWords.length;
      const cv=Math.sqrt(varp)/(avg||1);
      const scaled=Math.max(0,Math.min(1,(cv-0.1)/0.4)); // cv<=.1 full penalty; cv>=.5 none
      uniformity=1-scaled;
    }

    // readability
    const grade=fkGrade(t);
    const lowGrade=Math.max(0, (6 - Math.min(6,grade))/6);

    const metrics={rep:rep,burst:burst,invDiversity:invDiversity,cRate:cRate,hRate:hRate,passRate:passRate,buzzRate:buzzRate,uniformity:uniformity,lowGrade:lowGrade,wc:wc,sc:sc,grade:grade,types:types,topBig:topBig,cHits:cHits};
    const weights={rep:18,burst:9,invDiversity:12,cRate:14,hRate:6,passRate:6,buzzRate:10,uniformity:8,lowGrade:13};
    let score=0;
    for(const k in weights){
      let v=metrics[k];
      if(k==='cRate'||k==='hRate'||k==='buzzRate') v=Math.min(1,v/4);
      if(k==='passRate') v=Math.min(1,v/2);
      score+=v*weights[k];
    }
    score=Math.max(0,Math.min(100,score));
    return {ok:true,score:Math.round(score),metrics:metrics};
  }

  // ---------- UI ----------
  function resetUI(clearText=false){
    $('sli_score').textContent='—';
    $('sli_bar').style.width='0%';
    $('sli_highlights').innerHTML='';
    $('sli_signals').innerHTML='';
    $('sli_detections').textContent='—';
    $('sli_summary').textContent='';
    if(clearText){ $('sli_text').value=''; $('sli_url').value=''; }
    $('sli_status').textContent='';
  }

  function render(res){
    window.__vax_lastResult = res; // for lazy details fill

    const s=res.score;
    $('sli_score').textContent=s;
    $('sli_bar').style.width=s+'%';

    const hi=[];
    if(res.metrics.cHits.length){ hi.push('Clichés: '+res.metrics.cHits.map(x=>x[0]+'×'+x[1]).slice(0,6).join(', ')); }
    if(res.metrics.topBig.length){ hi.push('Top bigrams: '+res.metrics.topBig.map(x=>x[0]+'×'+x[1]).join(', ')); }
    $('sli_highlights').innerHTML=hi.map(x=>'<span class="tag">'+x.replace(/</g,'&lt;')+'</span>').join(' ');

    const sig=[
      ['Repetition', res.metrics.rep],
      ['Burstiness', res.metrics.burst],
      ['Low diversity', res.metrics.invDiversity],
      ['Clichés/100w', Math.min(1,res.metrics.cRate/4)],
      ['Hedges/100w', Math.min(1,res.metrics.hRate/4)],
      ['Passive/sentence', Math.min(1,res.metrics.passRate/2)],
      ['Buzzwords/100w', Math.min(1,res.metrics.buzzRate/4)],
      ['Uniformity', res.metrics.uniformity],
      ['Too-simple grade', res.metrics.lowGrade]
    ];

    // build signals HTML with tiny "?" hints
    var out = '';
    for(var i=0;i<sig.length;i++){
      var label=sig[i][0], val=sig[i][1], pct=Math.round(val*100);
      var tip = SIG_INFO[label] || '';
      out += '<div class="sig">'
          +   '<div class="hdr"><span class="lab">'+label+(tip? ' <button class="hint" data-tip="'+tip.replace(/"/g,'&quot;')+'" aria-label="What is '+label+'?">?</button>':'' )+'</span>'
          +   '<span>'+pct+'%</span></div>'
          +   '<div class="vax-sli__meter"><i style="width:'+pct+'%"></i></div>'
          + '</div>';
    }
    $('sli_signals').innerHTML = out;

    // short summary line
    const summary = (s>=70)
      ? 'High slop: loops + buzzword foam.'
      : (s>=50)
        ? 'Template fingerprints. Clean, but canned.'
        : (s>=30)
          ? 'Some slop; a human pass could breathe into it.'
          : 'Low slop. Either careful writing—or a very fussy bot.';
    const line='Slop Index '+s+'/100 — '+summary;
    $('sli_summary').textContent=line;

    // defer JSON details until <details> opens
    const detBox = $('sli_details');
    if(detBox && detBox.open){
      const det={score:s,words:res.metrics.wc,sentences:res.metrics.sc,fk_grade:res.metrics.grade.toFixed(2),unique_words:res.metrics.types,top_bigrams:res.metrics.topBig,cliche_hits:res.metrics.cHits};
      $('sli_detections').textContent = JSON.stringify(det,null,2);
    } else {
      $('sli_detections').textContent = '—';
    }

    // actions
    $('sli_copy').onclick=function(){
      if(navigator.clipboard){ navigator.clipboard.writeText(line).then(function(){ $('sli_status').textContent='Summary copied.'; setTimeout(function(){ $('sli_status').textContent='';},1000); }); }
    };
    $('sli_json').onclick=function(){
      var a=document.createElement('a');
      var blob=new Blob([JSON.stringify({ok:true,score:s,metrics:res.metrics},null,2)],{type:'application/json'});
      a.href=URL.createObjectURL(blob); a.download='slop-index.json'; a.click();
      setTimeout(function(){ URL.revokeObjectURL(a.href); },1500);
    };
  }

  // ---------- server fetch ----------
  function fetchUrl(url){
    $('sli_status').textContent='Fetching…';
    const p=new URLSearchParams(); p.set('action','vax_sli_fetch'); p.set('_wpnonce',VAX_SLI.nonce); p.set('url',url);
    return fetch(VAX_SLI.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:p})
      .then(r=>r.json())
      .then(j=>{
        if(!j.ok) throw new Error(j.error||'Fetch failed');
        $('sli_status').textContent='Fetched '+(j.bytes||'')+' bytes';
        const stripped=(j.body||'')
          .replace(/<script[\\s\\S]*?<\\/script>/gi,' ')
          .replace(/<style[\\s\\S]*?<\\/style>/gi,' ')
          .replace(/<[^>]+>/g,' ')
          .replace(/\\s+/g,' ')
          .trim();
        $('sli_text').value=stripped.slice(0,100000);
      });
  }

  // ---------- boot ----------
  function ready(){
    const SAMPLE="In today's fast-paced world, organizations are leveraging robust, scalable solutions to unlock the power of synergy. Our ultimate guide streamlines your experience with an intuitive, frictionless platform. It is designed to be leveraged by teams and was created to be optimized by experts. At the end of the day, this comprehensive guide empowers you to drive value. Click here to learn more.";
    const fetchBtn=$('sli_fetch'); if(!fetchBtn) return;

    // lazy fill details when opened the first time
    const detEl = $('sli_details');
    if(detEl){
      let filled=false;
      detEl.addEventListener('toggle', function(){
        if(detEl.open && !filled && window.__vax_lastResult){
          const r = window.__vax_lastResult;
          const det={score:r.score,words:r.metrics.wc,sentences:r.metrics.sc,fk_grade:r.metrics.grade.toFixed(2),unique_words:r.metrics.types,top_bigrams:r.metrics.topBig,cliche_hits:r.metrics.cHits};
          $('sli_detections').textContent = JSON.stringify(det,null,2);
          filled=true;
        }
      });
    }
document.addEventListener('click', (e)=>{
  const b = e.target.closest('.hint');
  if(b && b.hasAttribute('data-tip')){
    e.preventDefault();
    const was = b.classList.contains('open');
    document.querySelectorAll('.hint.open').forEach(x=>x.classList.remove('open'));
    if(!was) b.classList.add('open');
  } else {
    document.querySelectorAll('.hint.open').forEach(x=>x.classList.remove('open'));
  }
});
document.addEventListener('keydown', (e)=>{
  if(e.key === 'Escape') document.querySelectorAll('.hint.open').forEach(x=>x.classList.remove('open'));
});

    fetchBtn.addEventListener('click', ()=>{
      const u=$('sli_url').value.trim();
      if(!/^https?:\\/\\//i.test(u)){ $('sli_status').textContent='Enter a valid http(s) URL.'; return; }
      fetchUrl(u).catch(e=>{ $('sli_status').textContent=e.message; });
    });

    $('sli_url').addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); fetchBtn.click(); }});
    $('sli_sample').addEventListener('click', ()=>{ $('sli_text').value=SAMPLE; });
    $('sli_clear').addEventListener('click', ()=>resetUI(true));

    $('sli_analyze').addEventListener('click', ()=>{
      const t=$('sli_text').value||'';
      const res=analyze(t);
      if(!res.ok){ $('sli_status').textContent=res.error; return; }
      $('sli_status').textContent=''; render(res);
    });
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', ready); } else { ready(); }
})();
JS;

    wp_add_inline_script(self::HANDLE, $js, 'after');
  }

  // --- AJAX: fetch with SSRF guard + rate-limit + 10-min cache ---
  public static function ajax_fetch() {
    check_ajax_referer('vax_sli_nonce');

    $url = isset($_POST['url']) ? trim(wp_unslash($_POST['url'])) : '';
    if (!$url || !preg_match('~^https?://~i', $url)) {
      wp_send_json(['ok'=>false,'error'=>'Invalid URL']);
    }

    // SSRF guard
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) wp_send_json(['ok'=>false,'error'=>'Invalid host']);
    $ips = gethostbynamel($host) ?: [];
    foreach ($ips as $ip) {
      if (
        filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false ||
        in_array($ip, ['127.0.0.1','0.0.0.0'], true)
      ) {
        wp_send_json(['ok'=>false,'error'=>'Blocked host']);
      }
    }

    // Rate limit: 10 req / 60s / IP
    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rk   = 'vax_sli_rate_' . md5($ip);
    $hits = (int) get_transient($rk);
    if ($hits >= 10) wp_send_json(['ok'=>false,'error'=>'Rate limit: try again in a minute']);
    set_transient($rk, $hits + 1, 60);

    // Cache: 10 minutes per URL
    $ck = 'vax_sli_cache_' . md5($url);
    $cached = get_transient($ck);
    if (is_array($cached) && !empty($cached['body'])) {
      wp_send_json(['ok'=>true,'status'=>$cached['status'],'bytes'=>$cached['bytes'],'body'=>$cached['body'],'cached'=>true]);
    }

    // Fetch
    $args = [
      'timeout'     => 20,
      'redirection' => 5,
      'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36 VAX-Slop/1.1.2',
      'headers'     => ['Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
      'sslverify'   => true,
    ];
    $resp = wp_remote_get( esc_url_raw($url), $args );
    if (is_wp_error($resp)) wp_send_json(['ok'=>false, 'error'=>'Fetch failed: '.$resp->get_error_message()]);
    $code  = wp_remote_retrieve_response_code($resp);
    $body  = (string) wp_remote_retrieve_body($resp);
    $bytes = strlen($body);
    if ($bytes > 1000000) { $body = substr($body, 0, 1000000); }

    set_transient($ck, ['status'=>$code,'bytes'=>$bytes,'body'=>$body], MINUTE_IN_SECONDS * 10);
    wp_send_json(['ok'=>true,'status'=>$code,'bytes'=>$bytes,'body'=>$body,'cached'=>false]);
  }
}
VAX_Slop_Index::init();
