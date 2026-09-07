const mobileViewport=window.matchMedia('(max-width: 800px)');
const navigationEntry=performance.getEntriesByType('navigation')[0];
if(mobileViewport.matches&&navigationEntry?.type==='reload'){
  if('scrollRestoration'in history)history.scrollRestoration='manual';
  window.addEventListener('pageshow',()=>{
    window.scrollTo(0,0);
    window.setTimeout(()=>window.scrollTo(0,0),80);
  });
}

const header=document.querySelector('[data-header]');const menuButton=document.querySelector('[data-menu-toggle]');const nav=document.querySelector('[data-nav]');
const updateHeader=()=>header?.classList.toggle('scrolled',window.scrollY>20);updateHeader();window.addEventListener('scroll',updateHeader,{passive:true});
menuButton?.addEventListener('click',()=>{const open=menuButton.getAttribute('aria-expanded')==='true';menuButton.setAttribute('aria-expanded',String(!open));nav.classList.toggle('open',!open);document.body.classList.toggle('menu-open',!open)});
nav?.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{menuButton?.setAttribute('aria-expanded','false');nav.classList.remove('open');document.body.classList.remove('menu-open')}));
document.querySelectorAll('[data-year]').forEach(el=>el.textContent=new Date().getFullYear());

const themeStylesheet=document.querySelector('#theme-stylesheet');
const themeButtons=[...document.querySelectorAll('[data-theme-option]')];
const cookieConsentStorageKey='distillogic-cookie-consent';
const cookieConsentDuration=180*24*60*60*1000;
const readCookieConsent=()=>{
  try{
    const consent=JSON.parse(localStorage.getItem(cookieConsentStorageKey));
    if(!consent||Date.now()-new Date(consent.updatedAt).getTime()>cookieConsentDuration)return null;
    return consent;
  }catch{return null}
};
const preferenceStorageAllowed=()=>readCookieConsent()?.preferences===true;
const applyTheme=theme=>{
  if(!['light','dark'].includes(theme))return;
  document.documentElement.dataset.theme=theme;
  if(themeStylesheet)themeStylesheet.href=themeStylesheet.dataset[`${theme}Href`];
  document.querySelectorAll('[data-theme-logo]').forEach(logo=>logo.src=logo.dataset[`${theme}Src`]);
  if(preferenceStorageAllowed())localStorage.setItem('distillogic-theme',theme);
  themeButtons.forEach(button=>{
    const selected=button.dataset.themeOption===theme;
    button.classList.toggle('is-active',selected);
    button.setAttribute('aria-pressed',String(selected));
  });
};
themeButtons.forEach(button=>button.addEventListener('click',()=>applyTheme(button.dataset.themeOption)));
applyTheme(document.documentElement.dataset.theme||document.documentElement.dataset.defaultTheme);

const languageButtons=[...document.querySelectorAll('[data-language-option]')];
let requestedLanguage=localStorage.getItem('distillogic-language')||'en';

const updateLanguageControls=language=>{
  document.documentElement.lang=language;
  languageButtons.forEach(button=>{
    const selected=button.dataset.languageOption===language;
    button.classList.toggle('is-active',selected);
    button.setAttribute('aria-pressed',String(selected));
  });
};

const applyLanguage=language=>{
  if(!['en','el'].includes(language))return;
  requestedLanguage=language;
  if(preferenceStorageAllowed())localStorage.setItem('distillogic-language',language);
  updateLanguageControls(language);
  window.DISTILLOGIC_I18N?.setLanguage(language);
};
languageButtons.forEach(button=>button.addEventListener('click',()=>applyLanguage(button.dataset.languageOption)));
applyLanguage(requestedLanguage);

const projectEnquiryMarkup=`
  <div class="project-modal" data-project-modal hidden>
    <div class="project-modal-backdrop" data-project-modal-close></div>
    <section class="project-modal-card" role="dialog" aria-modal="true" aria-labelledby="project-modal-title">
      <header class="project-modal-header">
        <div>
          <p>Project Enquiry</p>
          <h2 id="project-modal-title">Discuss your project.</h2>
        </div>
        <button class="project-modal-close" type="button" aria-label="Close project enquiry" data-project-modal-close><span></span><span></span></button>
      </header>

      <div class="project-modal-body">
        <form class="project-enquiry-form" data-project-enquiry-form enctype="multipart/form-data">
          <section class="project-form-section">
            <div class="project-form-section-heading"><span>01</span><div><h3>Project Enquiry Form</h3><p>Tell us who you are and the type of support you are looking for.</p></div></div>
            <div class="project-form-grid">
              <label><span>Name</span><input type="text" name="name" autocomplete="name" required></label>
              <label><span>Company</span><input type="text" name="company" autocomplete="organization" required></label>
              <label><span>Business Email</span><input type="email" name="email" autocomplete="email" required></label>
              <label><span>Phone</span><input type="tel" name="phone" autocomplete="tel" maxlength="30"></label>
              <label><span>Country</span><input type="text" name="country" autocomplete="country-name" required></label>
              <label><span>What are you looking for?</span><select name="service" required><option value="">Select an option</option><option value="full-project-delivery">Full Project Delivery</option><option value="work-package-delivery">Work Package Delivery</option><option value="dedicated-engineering-team">Dedicated Engineering Team</option><option value="staff-augmentation">Staff Augmentation</option><option value="application-development">Application Development</option><option value="systems-integration">Systems Integration</option><option value="application-modernisation">Application Modernisation</option><option value="cloud-devops">Cloud / DevOps</option><option value="data-ai">Data &amp; AI</option><option value="quality-engineering">Quality Engineering</option><option value="application-management">Application Management</option><option value="other">Other</option></select></label>
            </div>
          </section>

          <section class="project-form-section">
            <div class="project-form-section-heading"><span>02</span><div><h3>Project Details</h3><p>Describe the requirement, delivery challenge or work package.</p></div></div>
            <div class="project-form-stack">
              <label><span>Project / Requirement Title</span><input type="text" name="projectTitle" required></label>
              <label><span>Tell us about your requirement</span><textarea name="requirement" rows="7" required></textarea></label>
            </div>
          </section>

          <section class="project-form-section">
            <div class="project-form-section-heading"><span>03</span><div><h3>Technology Stack</h3><p>Optional</p></div></div>
            <label><span>Current / Required Technologies</span><input type="text" name="technologies" placeholder="Java, Spring Boot, Angular, Azure — or Not yet defined"></label>
          </section>

          <section class="project-form-section">
            <div class="project-form-section-heading"><span>04</span><div><h3>Project Stage</h3></div></div>
            <label><span>Current stage</span><select name="projectStage" required><option value="">Select the current stage</option><option value="idea-planning">Idea / Initial Planning</option><option value="requirements-defined">Requirements Defined</option><option value="technical-design">Technical Design Available</option><option value="development-started">Development Started</option><option value="existing-application">Existing Application</option><option value="project-recovery">Project Recovery / Rescue</option><option value="procurement-rfp">Procurement / RFP Stage</option><option value="immediate-delivery">Immediate Delivery Requirement</option></select></label>
          </section>

          <section class="project-form-section">
            <div class="project-form-section-heading"><span>05</span><div><h3>Expected Engagement</h3></div></div>
            <label><span>Preferred engagement</span><select name="engagement" required><option value="">Select an engagement</option><option value="complete-project">Complete Project</option><option value="specific-work-package">Specific Work Package</option><option value="application-module">Application Module</option><option value="dedicated-team">Dedicated Team</option><option value="individual-specialists">Individual Specialists</option><option value="ongoing-support">Ongoing Application Support</option><option value="not-defined">Not Yet Defined</option></select></label>
          </section>

          <section class="project-form-section">
            <div class="project-form-section-heading"><span>06</span><div><h3>Estimated Timeline</h3></div></div>
            <label><span>Expected start</span><select name="timeline" required><option value="">Select a timeline</option><option value="immediate">Immediate</option><option value="within-one-month">Within 1 month</option><option value="one-three-months">1–3 months</option><option value="three-six-months">3–6 months</option><option value="six-plus-months">6+ months</option><option value="discuss">To be discussed</option></select></label>
          </section>

          <section class="project-form-section">
            <div class="project-form-section-heading"><span>07</span><div><h3>Supporting Documents</h3><p>Optional — RFP, scope, technical requirements, architecture, specifications or project brief.</p></div></div>
            <label class="project-file-field"><input type="file" name="documents" accept=".pdf,.doc,.docx,.xls,.xlsx" multiple data-project-files><span class="project-file-icon" aria-hidden="true">+</span><span><strong>Upload supporting documents</strong><small>PDF, DOCX or XLSX</small><small data-project-file-names>No files selected</small></span></label>
          </section>

          <section class="project-form-section project-nda-section">
            <div class="project-form-section-heading"><span>08</span><div><h3>Need an NDA before sharing project details?</h3><p>You can request a Non-Disclosure Agreement before providing confidential technical information.</p></div></div>
            <input type="hidden" name="requestNda" value="No" data-nda-value>
            <button class="request-nda-button" type="button" aria-pressed="false" data-request-nda>Request NDA</button>
          </section>

          <section class="project-submit-section">
            <div><p>Have a defined scope or RFP?</p><h3>Send us your project requirements and our team will review the technical and delivery needs.</h3></div>
            <label class="project-honeypot" aria-hidden="true"><span>Website</span><input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <button type="submit" disabled>Submit Project Requirement</button>
            <label class="project-consent"><input type="checkbox" name="privacyConsent" value="yes" required><span>I agree to the processing of my information for the purpose of this project enquiry.</span></label>
            <p class="project-form-status" role="status" aria-live="polite" data-project-form-status></p>
          </section>
        </form>

      </div>
    </section>
  </div>
`;

document.body.insertAdjacentHTML('beforeend',projectEnquiryMarkup);

const projectModal=document.querySelector('[data-project-modal]');
const projectModalCard=projectModal?.querySelector('.project-modal-card');
const projectModalBody=projectModal?.querySelector('.project-modal-body');
const projectModalCloseButtons=[...document.querySelectorAll('[data-project-modal-close]')];
const projectOpenButtons=[...document.querySelectorAll('.nav-cta,.mobile-project-cta,[data-project-enquiry-open]')];
const projectForm=document.querySelector('[data-project-enquiry-form]');
const projectFileInput=document.querySelector('[data-project-files]');
const projectFileNames=document.querySelector('[data-project-file-names]');
const ndaButton=document.querySelector('[data-request-nda]');
const ndaValue=document.querySelector('[data-nda-value]');
const projectFormStatus=document.querySelector('[data-project-form-status]');
const projectConsent=projectForm?.querySelector('input[name="privacyConsent"]');
const projectSubmitButton=projectForm?.querySelector('button[type="submit"]');
let projectScrollPosition=0;
let projectLastFocus=null;

const openProjectModal=()=>{
  if(!projectModal)return;
  projectScrollPosition=window.scrollY;
  projectLastFocus=document.activeElement;
  document.body.style.top=`-${projectScrollPosition}px`;
  document.body.classList.add('project-modal-open');
  projectModal.hidden=false;
  requestAnimationFrame(()=>{
    projectModal.classList.add('is-open');
    projectModal.querySelector('.project-modal-close')?.focus();
  });
};

const closeProjectModal=()=>{
  if(!projectModal||projectModal.hidden)return;
  projectModal.classList.remove('is-open');
  document.body.classList.remove('project-modal-open');
  document.body.style.top='';
  window.scrollTo(0,projectScrollPosition);
  window.setTimeout(()=>{
    projectModal.hidden=true;
    projectLastFocus?.focus?.();
  },220);
};

projectOpenButtons.forEach(button=>button.addEventListener('click',event=>{
  event.preventDefault();
  openProjectModal();
}));

projectModalCloseButtons.forEach(button=>button.addEventListener('click',closeProjectModal));

projectModal?.addEventListener('keydown',event=>{
  if(event.key==='Escape'){
    closeProjectModal();
    return;
  }
  if(event.key!=='Tab')return;
  const focusable=[...projectModal.querySelectorAll('button,input,select,textarea,[href]:not([tabindex="-1"])')].filter(element=>!element.disabled&&element.offsetParent!==null);
  if(!focusable.length)return;
  const first=focusable[0];
  const last=focusable[focusable.length-1];
  if(event.shiftKey&&document.activeElement===first){
    event.preventDefault();
    last.focus();
  }else if(!event.shiftKey&&document.activeElement===last){
    event.preventDefault();
    first.focus();
  }
});

projectFileInput?.addEventListener('change',()=>{
  const files=[...projectFileInput.files];
  projectFileNames.textContent=files.length?files.map(file=>file.name).join(', '):'No files selected';
});

ndaButton?.addEventListener('click',()=>{
  const selected=ndaButton.getAttribute('aria-pressed')!=='true';
  ndaButton.setAttribute('aria-pressed',String(selected));
  ndaButton.classList.toggle('is-selected',selected);
  ndaButton.textContent=selected?'NDA Requested':'Request NDA';
  if(ndaValue)ndaValue.value=selected?'Yes':'No';
});

const syncProjectSubmitState=()=>{
  if(projectSubmitButton)projectSubmitButton.disabled=!projectConsent?.checked;
};

projectConsent?.addEventListener('change',syncProjectSubmitState);
syncProjectSubmitState();

projectForm?.addEventListener('submit',async event=>{
  event.preventDefault();
  if(!projectForm.reportValidity())return;

  const submitButton=projectSubmitButton;
  const originalButtonText=submitButton?.textContent||'Submit Project Requirement';
  if(submitButton){
    submitButton.disabled=true;
    submitButton.textContent='Submitting…';
  }
  projectFormStatus.textContent='Submitting your project enquiry…';

  const payload=new FormData(projectForm);
  payload.set('language',document.documentElement.lang==='el'?'el':'en');
  payload.set('sourcePage',window.location.pathname);

  try{
    const response=await fetch('/api/project-enquiries',{
      method:'POST',
      headers:{Accept:'application/json'},
      body:payload
    });
    const result=await response.json().catch(()=>({}));
    if(!response.ok){
      const isGreek=document.documentElement.lang==='el';
      const failureNotice=result.failureEmailSent
        ?(isGreek?' Σας στείλαμε και σχετική ενημέρωση μέσω email.':' We also sent you a failure notification by email.')
        :'';
      throw new Error((result.error||'The request could not be processed. Please try again.')+failureNotice);
    }

    const isGreek=document.documentElement.lang==='el';
    if(result.stored===false){
      projectFormStatus.textContent='The backend validated the enquiry in test mode. No data was stored or sent.';
    }else{
      projectFormStatus.textContent=result.confirmationEmailSent
        ?(isGreek
          ?`Η αίτηση υποβλήθηκε επιτυχώς και σας στείλαμε email επιβεβαίωσης. Κωδικός: ${result.reference}`
          :`Your project enquiry was submitted successfully and a confirmation email was sent. Reference: ${result.reference}`)
        :(isGreek
          ?`Η αίτηση καταχωρίστηκε επιτυχώς, αλλά δεν ήταν δυνατή η αποστολή email επιβεβαίωσης. Κωδικός: ${result.reference}`
          :`Your project enquiry was submitted successfully, but the confirmation email could not be sent. Reference: ${result.reference}`);
      projectForm.reset();
      syncProjectSubmitState();
      projectFileNames.textContent='No files selected';
      ndaButton?.setAttribute('aria-pressed','false');
      ndaButton?.classList.remove('is-selected');
      if(ndaButton)ndaButton.textContent='Request NDA';
      if(ndaValue)ndaValue.value='No';
    }
  }catch(error){
    projectFormStatus.textContent=error instanceof TypeError
      ?'The server could not be reached. Please try again later.'
      :error.message;
  }finally{
    if(submitButton){
      submitButton.disabled=!projectConsent?.checked;
      submitButton.textContent=originalButtonText;
    }
    projectModalBody?.scrollTo({top:projectModalBody.scrollHeight,behavior:'smooth'});
  }
});

const cookieConsentMarkup=`
  <section class="cookie-banner" data-cookie-banner hidden aria-label="Cookie notice">
    <div><p>Privacy controls</p><h2>Your privacy, your choice.</h2><span>We currently use necessary storage and optional preferences for language and theme. Analytics and marketing technologies are not active.</span></div>
    <div class="cookie-banner-actions"><button type="button" data-cookie-reject>Reject Non-Essential</button><button type="button" data-cookie-settings>Manage Preferences</button><button class="cookie-primary" type="button" data-cookie-accept>Accept All</button></div>
  </section>
  <div class="cookie-settings-modal" data-cookie-modal hidden>
    <div class="cookie-settings-backdrop" data-cookie-close></div>
    <section class="cookie-settings-card" role="dialog" aria-modal="true" aria-labelledby="cookie-settings-title">
      <header><div><p>Cookie controls</p><h2 id="cookie-settings-title">Manage your preferences.</h2></div><button type="button" aria-label="Close cookie settings" data-cookie-close>×</button></header>
      <div class="cookie-settings-body">
        <p>Optional technologies remain disabled unless you choose to enable them. You can change your selection at any time.</p>
        <label><span><strong>Strictly Necessary</strong><small>Consent records and functionality required to provide the website.</small></span><input type="checkbox" checked disabled aria-label="Strictly necessary storage is always active"></label>
        <label><span><strong>Preferences</strong><small>Remembers your language and light or dark theme selection.</small></span><input type="checkbox" data-cookie-preferences></label>
        <label class="is-unavailable"><span><strong>Analytics</strong><small>Not currently used on this website.</small></span><input type="checkbox" disabled aria-label="Analytics technologies are not currently used"></label>
        <label class="is-unavailable"><span><strong>Marketing</strong><small>Not currently used on this website.</small></span><input type="checkbox" disabled aria-label="Marketing technologies are not currently used"></label>
      </div>
      <footer><a href="cookies.html">Read Cookie Policy</a><button class="cookie-primary" type="button" data-cookie-save>Save Preferences</button></footer>
    </section>
  </div>
`;

document.body.insertAdjacentHTML('beforeend',cookieConsentMarkup);
document.querySelectorAll('.footer-legal').forEach(footer=>{
  if(footer.querySelector('[data-cookie-settings]'))return;
  const settingsButton=document.createElement('button');
  settingsButton.type='button';
  settingsButton.dataset.cookieSettings='';
  settingsButton.textContent='Cookie Settings';
  footer.append(settingsButton);
});

const cookieBanner=document.querySelector('[data-cookie-banner]');
const cookieModal=document.querySelector('[data-cookie-modal]');
const cookiePreferenceInput=document.querySelector('[data-cookie-preferences]');
let cookieSettingsLastFocus=null;

const closeCookieSettings=()=>{
  if(!cookieModal)return;
  cookieModal.hidden=true;
  document.body.classList.remove('cookie-settings-open');
  cookieSettingsLastFocus?.focus?.();
};

const openCookieSettings=()=>{
  if(!cookieModal)return;
  cookieSettingsLastFocus=document.activeElement;
  cookiePreferenceInput.checked=readCookieConsent()?.preferences===true;
  cookieModal.hidden=false;
  document.body.classList.add('cookie-settings-open');
  cookieModal.querySelector('[data-cookie-save]')?.focus();
};

const saveCookieConsent=preferences=>{
  const consent={version:1,necessary:true,preferences:Boolean(preferences),analytics:false,marketing:false,updatedAt:new Date().toISOString()};
  localStorage.setItem(cookieConsentStorageKey,JSON.stringify(consent));
  if(consent.preferences){
    localStorage.setItem('distillogic-theme',document.documentElement.dataset.theme||'light');
    localStorage.setItem('distillogic-language',document.documentElement.lang==='el'?'el':'en');
  }else{
    localStorage.removeItem('distillogic-theme');
    localStorage.removeItem('distillogic-language');
  }
  cookieBanner.hidden=true;
  closeCookieSettings();
};

document.querySelector('[data-cookie-accept]')?.addEventListener('click',()=>saveCookieConsent(true));
document.querySelector('[data-cookie-reject]')?.addEventListener('click',()=>saveCookieConsent(false));
document.querySelectorAll('[data-cookie-settings]').forEach(button=>button.addEventListener('click',openCookieSettings));
document.querySelectorAll('[data-cookie-close]').forEach(button=>button.addEventListener('click',closeCookieSettings));
document.querySelector('[data-cookie-save]')?.addEventListener('click',()=>saveCookieConsent(cookiePreferenceInput.checked));
document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!cookieModal?.hidden)closeCookieSettings()});

const currentCookieConsent=readCookieConsent();
if(!currentCookieConsent){
  localStorage.removeItem(cookieConsentStorageKey);
  localStorage.removeItem('distillogic-theme');
  localStorage.removeItem('distillogic-language');
  cookieBanner.hidden=false;
}

const backToTopButton=document.createElement('button');
backToTopButton.type='button';
backToTopButton.className='back-to-top';
backToTopButton.setAttribute('aria-label','Back to top');
backToTopButton.setAttribute('title','Back to top');
backToTopButton.innerHTML='<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 14.5 12 7l7 7.5M12 7v11"/></svg>';
document.body.append(backToTopButton);

const backToTopReducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)');
const updateBackToTop=()=>{
  const pageCanScroll=document.documentElement.scrollHeight>window.innerHeight+160;
  backToTopButton.hidden=!pageCanScroll;
  backToTopButton.classList.toggle('is-visible',pageCanScroll&&window.scrollY>Math.max(420,window.innerHeight*.55));
};

backToTopButton.addEventListener('click',()=>window.scrollTo({top:0,behavior:backToTopReducedMotion.matches?'auto':'smooth'}));
window.addEventListener('scroll',updateBackToTop,{passive:true});
window.addEventListener('resize',updateBackToTop);
window.addEventListener('load',updateBackToTop);
new ResizeObserver(updateBackToTop).observe(document.body);
updateBackToTop();
