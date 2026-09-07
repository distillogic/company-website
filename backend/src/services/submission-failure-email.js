import {config} from '../config.js';

const escapeHtml=(value)=>String(value).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');

export const sendSubmissionFailureEmail=async({reference,data,files})=>{
  if(!config.resendApiKey||!config.resendFromEmail)return false;

  const greek=data.language==='el';
  const title=greek?'Το αίτημα έργου σας δεν υποβλήθηκε':'Your project enquiry could not be submitted';
  const intro=greek
    ?'Λάβαμε την προσπάθεια υποβολής, αλλά δεν μπορέσαμε να καταχωρίσουμε το αίτημα στο CRM. Δεν δημιουργήθηκε επιτυχής αίτηση. Δοκιμάστε ξανά αργότερα ή επικοινωνήστε στο info@distillogic.gr.'
    :'We received your submission attempt, but could not register it in the CRM. No successful application was created. Please try again later or contact info@distillogic.gr.';
  const labels=greek
    ?['Κωδικός','Όνομα','Εταιρεία','Email','Τίτλος έργου','Απαιτήσεις','Συνημμένα']
    :['Reference','Name','Company','Email','Project title','Requirements','Attachments'];
  const values=[reference,data.name,data.company,data.email,data.projectTitle,data.requirement,files.length?files.map(file=>file.originalname).join(', '):(greek?'Κανένα':'None')];
  const preview=labels.map((label,index)=>`${label}: ${values[index]}`).join('\n');
  const rows=labels.map((label,index)=>`<tr><th align="left" valign="top" style="padding:10px;border-bottom:1px solid #d9e3ef;color:#58708b;font:600 12px Arial,sans-serif;">${escapeHtml(label)}</th><td style="padding:10px;border-bottom:1px solid #d9e3ef;color:#102a43;font:14px/20px Arial,sans-serif;white-space:pre-wrap;">${escapeHtml(values[index])}</td></tr>`).join('');

  try{
    const response=await fetch('https://api.resend.com/emails',{
      method:'POST',
      headers:{
        Authorization:`Bearer ${config.resendApiKey}`,
        'Content-Type':'application/json',
        'Idempotency-Key':`website-enquiry/${reference}/failed-at-gateway`
      },
      signal:AbortSignal.timeout(10000),
      body:JSON.stringify({
        from:`DISTILLOGIC TECHNOLOGIES <${config.resendFromEmail}>`,
        reply_to:config.resendFromEmail,
        to:[data.email],
        subject:`${title} — ${reference}`,
        text:`${title}\n\n${intro}\n\n${preview}`,
        html:`<!doctype html><html lang="${greek?'el':'en'}"><body style="margin:0;background:#f3f7fb;padding:24px;"><div style="max-width:680px;margin:auto;padding:34px;background:#fff;border:1px solid #d9e3ef;border-top:5px solid #b42318;border-radius:16px;font-family:Arial,sans-serif;color:#102a43;"><div style="font-size:12px;font-weight:700;letter-spacing:1.4px;color:#b42318;">DISTILLOGIC TECHNOLOGIES</div><h1 style="font-size:27px;line-height:34px;">${escapeHtml(title)}</h1><p style="color:#486581;font-size:15px;line-height:24px;">${escapeHtml(intro)}</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px;border:1px solid #d9e3ef;">${rows}</table></div></body></html>`
      })
    });
    return response.ok;
  }catch{
    return false;
  }
};
