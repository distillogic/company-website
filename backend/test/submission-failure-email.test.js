import test from 'node:test';
import assert from 'node:assert/strict';

process.env.RESEND_API_KEY='test-api-key';
process.env.RESEND_FROM_EMAIL='info@distillogic.gr';

const {sendSubmissionFailureEmail}=await import('../src/services/submission-failure-email.js');

test('failure email contains a safe application preview',async(t)=>{
  const originalFetch=globalThis.fetch;
  let request;
  globalThis.fetch=async(url,options)=>{
    request={url,options};
    return new Response(null,{status:200});
  };
  t.after(()=>{globalThis.fetch=originalFetch;});

  const sent=await sendSubmissionFailureEmail({
    reference:'DL-A1B2C3D4',
    data:{
      language:'en',name:'Test User',company:'Example & Partners',
      email:'test@example.com',projectTitle:'Customer <Portal>',
      requirement:'Secure delivery'
    },
    files:[{originalname:'requirements.pdf'}]
  });

  assert.equal(sent,true);
  assert.equal(request.url,'https://api.resend.com/emails');
  const body=JSON.parse(request.options.body);
  assert.deepEqual(body.to,['test@example.com']);
  assert.match(body.subject,/could not be submitted/);
  assert.match(body.text,/requirements\.pdf/);
  assert.match(body.html,/Example &amp; Partners/);
  assert.match(body.html,/Customer &lt;Portal&gt;/);
  assert.doesNotMatch(body.html,/Customer <Portal>/);
});
