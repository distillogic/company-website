const serviceIds=['software-engineering','systems-integration','application-modernisation','cloud-platform-engineering','data-ai-engineering','quality-engineering','application-management','technology-delivery'];
const softwarePanelIds=['software-deliverables','typical-projects','delivery-models'];
const serviceLinks=[...document.querySelectorAll('.service-jump a')];
const serviceJump=document.querySelector('.service-jump');
const serviceTrack=document.querySelector('.service-jump-inner');
const serviceSelect=document.querySelector('[data-service-select]');
const serviceScrollButtons=[...document.querySelectorAll('[data-service-scroll]')];
const serviceOverview=document.querySelector('[data-service-overview]');
const serviceEyebrow=document.querySelector('[data-service-eyebrow]');
const serviceTitle=document.querySelector('[data-service-title]');
const serviceDescription=document.querySelector('[data-service-description]');

const serviceOverviewContent={
  'software-engineering':{
    eyebrow:'Software Engineering',
    title:'Engineering scalable software for complex business requirements.',
    description:[
      'Custom software engineering transforms a defined business requirement into a reliable application, platform, service or module designed for its real operating environment.',
      'We work across new product development, enterprise application delivery and the enhancement of existing software.'
    ]
  },
  'systems-integration':{
    eyebrow:'Systems Integration',
    title:'Connecting applications, platforms and enterprise systems.',
    description:['DISTILLOGIC designs and delivers integration solutions that enable applications, enterprise systems and third-party platforms to exchange information securely and reliably.']
  },
  'application-modernisation':{
    eyebrow:'Application Modernisation',
    title:'Transforming existing applications for modern technology environments.',
    description:['DISTILLOGIC helps organisations modernise legacy applications, architectures and technology stacks while preserving critical business functionality.']
  },
  'cloud-platform-engineering':{
    eyebrow:'Cloud & Platform Engineering',
    title:'Building scalable, automated and reliable cloud environments.',
    description:['DISTILLOGIC supports organisations in designing, deploying and operating modern cloud and platform environments for software applications and digital services.']
  },
  'data-ai-engineering':{
    eyebrow:'Data & AI Engineering',
    title:'Turning enterprise data into intelligent systems and actionable information.',
    description:['DISTILLOGIC designs and develops data platforms, analytics solutions and AI-enabled applications that support automation, decision-making and digital operations.']
  },
  'quality-engineering':{
    eyebrow:'Quality Engineering',
    title:'Building quality into every stage of software delivery.',
    description:['DISTILLOGIC provides structured software testing and quality engineering services to improve reliability, performance and release confidence.']
  },
  'application-management':{
    eyebrow:'Application Management',
    title:'Keeping business-critical applications stable, current and continuously improving.',
    description:['DISTILLOGIC provides ongoing engineering support, maintenance and enhancement services throughout the application lifecycle.']
  },
  'technology-delivery':{
    eyebrow:'Technology Delivery',
    title:'Flexible engineering delivery for complex technology programmes.',
    description:['DISTILLOGIC works with enterprises, system integrators and technology organisations to provide complete project delivery, defined work packages and dedicated engineering capacity.']
  }
};

const getServicePanels=id=>{
  if(id==='software-engineering')return softwarePanelIds.map(panelId=>document.getElementById(panelId)).filter(Boolean);
  const panel=document.getElementById(id);
  return panel?[panel]:[];
};

const allServicePanels=[...new Set(serviceIds.flatMap(getServicePanels))];

const updateServiceArrows=()=>{
  if(!serviceTrack)return;
  const maxScroll=Math.max(0,serviceTrack.scrollWidth-serviceTrack.clientWidth);
  const atStart=serviceTrack.scrollLeft<=2;
  const atEnd=serviceTrack.scrollLeft>=maxScroll-2;

  serviceScrollButtons.forEach(button=>{
    const direction=Number(button.dataset.serviceScroll);
    const unavailable=maxScroll<=2||(direction<0?atStart:atEnd);
    button.classList.toggle('is-unavailable',unavailable);
    button.disabled=unavailable;
  });
};

const revealActiveServiceLink=activeLink=>{
  if(!serviceTrack||!activeLink||window.innerWidth<=800)return;
  const targetLeft=activeLink.offsetLeft-(serviceTrack.clientWidth-activeLink.offsetWidth)/2;
  serviceTrack.scrollTo({
    left:Math.max(0,targetLeft),
    behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'
  });
  window.setTimeout(updateServiceArrows,320);
};

const activateService=(id,{updateUrl=false,moveToContent=false}={})=>{
  const activeId=serviceIds.includes(id)?id:'software-engineering';
  const activePanels=getServicePanels(activeId);
  const overview=serviceOverviewContent[activeId];

  if(overview){
    serviceEyebrow.textContent=overview.eyebrow;
    serviceTitle.textContent=overview.title;
    serviceDescription.replaceChildren(...overview.description.map(text=>{
      const paragraph=document.createElement('p');
      paragraph.textContent=text;
      return paragraph;
    }));
  }

  allServicePanels.forEach(panel=>{
    const isActive=activePanels.includes(panel);
    panel.hidden=!isActive;
    panel.classList.toggle('is-active-service',isActive);
  });

  serviceLinks.forEach(link=>{
    const selected=link.getAttribute('href')===`#${activeId}`;
    link.classList.toggle('is-active',selected);
    link.setAttribute('aria-selected',String(selected));
    link.setAttribute('tabindex',selected?'0':'-1');
  });

  if(serviceSelect)serviceSelect.value=activeId;
  revealActiveServiceLink(serviceLinks.find(link=>link.classList.contains('is-active')));

  if(updateUrl)history.pushState({service:activeId},'',`#${activeId}`);

  if(moveToContent&&serviceOverview){
    const headerHeight=window.innerWidth<=800?172:88;
    const offset=headerHeight+(serviceJump?.offsetHeight||0)+16;
    const top=serviceOverview.getBoundingClientRect().top+window.scrollY-offset;
    window.scrollTo({top,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
  }
};

serviceTrack?.setAttribute('role','tablist');
serviceLinks.forEach((link,index)=>{
  link.setAttribute('role','tab');
  link.addEventListener('click',event=>{
    event.preventDefault();
    activateService(link.hash.slice(1),{updateUrl:true,moveToContent:true});
  });
  link.addEventListener('keydown',event=>{
    if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
    event.preventDefault();
    let targetIndex=index;
    if(event.key==='ArrowRight')targetIndex=(index+1)%serviceLinks.length;
    if(event.key==='ArrowLeft')targetIndex=(index-1+serviceLinks.length)%serviceLinks.length;
    if(event.key==='Home')targetIndex=0;
    if(event.key==='End')targetIndex=serviceLinks.length-1;
    serviceLinks[targetIndex].focus();
    serviceLinks[targetIndex].click();
  });
});

serviceScrollButtons.forEach(button=>{
  button.addEventListener('click',()=>{
    if(!serviceTrack)return;
    serviceTrack.scrollBy({
      left:Number(button.dataset.serviceScroll)*serviceTrack.clientWidth*.72,
      behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'
    });
  });
});

serviceSelect?.addEventListener('change',()=>{
  activateService(serviceSelect.value,{updateUrl:true,moveToContent:true});
});

serviceTrack?.addEventListener('scroll',updateServiceArrows,{passive:true});
window.addEventListener('resize',updateServiceArrows);

let previousPageY=window.scrollY;
let serviceJumpFramePending=false;

const updateServiceJumpVisibility=()=>{
  const currentPageY=Math.max(0,window.scrollY);
  const movement=currentPageY-previousPageY;

  if(currentPageY<=48){
    serviceJump?.classList.remove('is-scroll-hidden');
  }else if(movement>6){
    serviceJump?.classList.add('is-scroll-hidden');
  }else if(movement<-6){
    serviceJump?.classList.remove('is-scroll-hidden');
  }

  previousPageY=currentPageY;
  serviceJumpFramePending=false;
};

window.addEventListener('scroll',()=>{
  if(serviceJumpFramePending)return;
  serviceJumpFramePending=true;
  requestAnimationFrame(updateServiceJumpVisibility);
},{passive:true});

const initialService=serviceIds.includes(location.hash.slice(1))?location.hash.slice(1):'software-engineering';
activateService(initialService);
requestAnimationFrame(updateServiceArrows);
window.addEventListener('popstate',()=>activateService(serviceIds.includes(location.hash.slice(1))?location.hash.slice(1):'software-engineering'));

const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealElements=document.querySelectorAll('.reveal');

if(reducedMotion){
  revealElements.forEach(element=>element.classList.add('visible'));
}else{
  const revealObserver=new IntersectionObserver(entries=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  },{threshold:.1});
  revealElements.forEach(element=>revealObserver.observe(element));
}
