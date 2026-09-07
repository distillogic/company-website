const revealObserver=new IntersectionObserver((entries)=>{entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('visible');revealObserver.unobserve(entry.target)}})},{threshold:.12,rootMargin:'0px 0px -45px'});
document.querySelectorAll('.reveal').forEach((element,index)=>{element.style.transitionDelay=`${Math.min(index%4,3)*70}ms`;revealObserver.observe(element)});

const actionObserver=new IntersectionObserver((entries)=>{entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('action-visible');actionObserver.unobserve(entry.target)}})},{threshold:.25});
document.querySelectorAll('.button,.nav-cta,.service-card a,.section-link,.dark-section-link').forEach((action,index)=>{action.style.animationDelay=`${Math.min(index%4,3)*60}ms`;actionObserver.observe(action)});

const credibilityTrack=document.querySelector('.credibility-grid');
const credibilityItems=[...(credibilityTrack?.querySelectorAll(':scope > span')||[])];
const mobileCredibility=window.matchMedia('(max-width: 800px)');
const reducedMotionPreference=window.matchMedia('(prefers-reduced-motion: reduce)');
let credibilityIndex=0;
let credibilityTimer;

const showCredibilityItem=(index)=>{
  credibilityItems.forEach((item,itemIndex)=>{
    const active=itemIndex===index;
    const secondary=itemIndex===(index+1)%credibilityItems.length;
    item.classList.toggle('is-active',active);
    item.classList.toggle('is-secondary',secondary);
    item.setAttribute('aria-hidden',String(!active&&!secondary));
  });
};

const stopCredibilityCarousel=()=>{
  window.clearInterval(credibilityTimer);
  credibilityTimer=undefined;
};

const updateCredibilityCarousel=()=>{
  stopCredibilityCarousel();
  if(!credibilityItems.length)return;
  if(!mobileCredibility.matches){
    credibilityItems.forEach(item=>{item.classList.remove('is-active','is-secondary');item.removeAttribute('aria-hidden')});
    return;
  }
  credibilityIndex%=credibilityItems.length;
  showCredibilityItem(credibilityIndex);
  if(reducedMotionPreference.matches)return;
  credibilityTimer=window.setInterval(()=>{
    credibilityIndex=(credibilityIndex+2)%credibilityItems.length;
    showCredibilityItem(credibilityIndex);
  },1000);
};

updateCredibilityCarousel();
mobileCredibility.addEventListener('change',updateCredibilityCarousel);
reducedMotionPreference.addEventListener('change',updateCredibilityCarousel);
document.addEventListener('visibilitychange',()=>document.hidden?stopCredibilityCarousel():updateCredibilityCarousel());

const deliveryModels=[...document.querySelectorAll('.model')];
const selectDeliveryModel=(selectedModel)=>{
  deliveryModels.forEach(model=>{
    const selected=model===selectedModel;
    model.classList.toggle('is-active',selected);
    model.setAttribute('aria-pressed',String(selected));
  });
};

deliveryModels.forEach((model,index)=>{
  model.tabIndex=0;
  model.setAttribute('role','button');
  model.setAttribute('aria-pressed','false');
  model.addEventListener('click',()=>selectDeliveryModel(model));
  model.addEventListener('keydown',event=>{
    if(event.key==='Enter'||event.key===' '){
      event.preventDefault();
      selectDeliveryModel(model);
    }
  });
});

const technologyLogos=[
  [['openjdk','Java'],['spring','Spring Boot'],['dotnet','.NET'],['sharp','C#'],['nodedotjs','Node.js'],['python','Python']],
  [['react','React'],['nextdotjs','Next.js'],['angular','Angular'],['vuedotjs','Vue'],['typescript','TypeScript']],
  [['microsoftazure','Azure'],['amazonwebservices','AWS'],['googlecloud','Google Cloud'],['docker','Docker'],['kubernetes','Kubernetes'],['terraform','Terraform']],
  [['python','Python'],['apachespark','Spark'],['databricks','Databricks'],['snowflake','Snowflake'],['llm','LLM / RAG'],['scikitlearn','Machine Learning']],
  [['openapiinitiative','REST / OpenAPI'],['graphql','GraphQL'],['grpc','gRPC'],['apachekafka','Kafka'],['rabbitmq','RabbitMQ']],
  [['playwright','Playwright'],['cypress','Cypress'],['selenium','Selenium'],['postman','Postman'],['apachejmeter','JMeter']]
];
const localTechnologyLogos={
  microsoftazure:'azure',
  amazonwebservices:'aws'
};

document.querySelectorAll('.technology-grid article').forEach((card,index)=>{
  const description=card.querySelector('p');
  const logos=technologyLogos[index];
  if(!description||!logos)return;
  const logoGrid=document.createElement('div');
  logoGrid.className='tech-logos';
  logos.forEach(([slug,name])=>{
    const logo=document.createElement('span');
    logo.className='tech-logo';
    logo.dataset.label=name;
    logo.setAttribute('aria-label',name);
    logo.setAttribute('role','button');
    logo.tabIndex=0;
    const image=document.createElement('img');
    image.src=`assets/icons/${localTechnologyLogos[slug]||slug}.svg`;
    image.alt='';
    image.loading='lazy';
    logo.append(image);
    const toggleLogoTooltip=()=>{
      if(!window.matchMedia('(hover: none)').matches)return;
      const opening=!logo.classList.contains('is-tooltip-open');
      document.querySelectorAll('.tech-logo.is-tooltip-open').forEach(item=>item.classList.remove('is-tooltip-open'));
      logo.classList.toggle('is-tooltip-open',opening);
      logo.setAttribute('aria-expanded',String(opening));
    };
    logo.addEventListener('click',event=>{event.stopPropagation();toggleLogoTooltip()});
    logo.addEventListener('keydown',event=>{
      if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleLogoTooltip()}
    });
    logoGrid.append(logo);
  });
  description.replaceWith(logoGrid);
});

document.addEventListener('click',()=>{
  document.querySelectorAll('.tech-logo.is-tooltip-open').forEach(item=>{
    item.classList.remove('is-tooltip-open');
    item.setAttribute('aria-expanded','false');
  });
});

const deliverableIconPaths=[
  '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 8h18"/><circle cx="9" cy="13" r="2"/><path d="M6 18c.7-2 5.3-2 6 0"/>',
  '<path d="m8 12 3 3 5-6"/><path d="M4 8h4l2 2 2-2h8v10H4z"/><path d="M8 5h8"/>',
  '<path d="M4 21V7l8-4 8 4v14"/><path d="M9 21v-5h6v5M8 9h.01M12 9h.01M16 9h.01M8 13h.01M16 13h.01"/>',
  '<path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"/><path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"/>',
  '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 12a4 4 0 0 1 7-2l1-2v4h-4l1.5-1.5M16 14a4 4 0 0 1-7 2l-1 2v-4h4l-1.5 1.5"/>',
  '<path d="M6 19h11a4 4 0 0 0 .8-7.9A6 6 0 0 0 6.3 9.5 4.8 4.8 0 0 0 6 19Z"/><circle cx="9" cy="14" r="1"/><circle cx="15" cy="15" r="1"/><path d="m10 14 4 1"/>',
  '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
  '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="3" r="1"/><circle cx="21" cy="12" r="1"/><circle cx="12" cy="21" r="1"/><circle cx="3" cy="12" r="1"/><path d="m12 4 0 5m8 3h-5m-3 8v-5m-8-3h5"/>',
  '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-5"/>',
  '<path d="M14.7 6.3a4 4 0 0 0-5 5L3 18v3h3l6.7-6.7a4 4 0 0 0 5-5l-3 3-3-3 3-3Z"/><path d="m17 17 1 2 2 1-2 1-1 2-1-2-2-1 2-1Z"/>'
];

document.querySelectorAll('.deliverable-grid>span').forEach((item,index)=>{
  const icon=document.createElement('span');
  icon.className='deliverable-icon';
  icon.setAttribute('aria-hidden','true');
  icon.innerHTML=`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${deliverableIconPaths[index]}</svg>`;
  item.prepend(icon);
});

const industryCards=[...document.querySelectorAll('.industry-list a')];
industryCards.forEach(card=>{
  card.addEventListener('click',event=>{
    if(!window.matchMedia('(hover: none)').matches)return;
    event.preventDefault();
    const opening=!card.classList.contains('is-active');
    industryCards.forEach(item=>item.classList.remove('is-active'));
    card.classList.toggle('is-active',opening);
  });
});

const industryIconPaths=[
  '<path d="m3 10 9-5 9 5"/><path d="M5 10h14M6 10v8m4-8v8m4-8v8m4-8v8M4 21h16"/>',
  '<path d="m13 2-9 12h8l-1 8 9-12h-8z"/>',
  '<path d="M12 18v4M8 22h8M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M7.8 17.5a7 7 0 0 1 0-11M16.2 6.5a7 7 0 0 1 0 11M4.5 20.5a12 12 0 0 1 0-17M19.5 3.5a12 12 0 0 1 0 17"/>',
  '<path d="M3 6h11v11H3zM14 10h4l3 3v4h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
  '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 21v-5h8v5M8 7h2m4 0h2M8 11h2m4 0h2"/>',
  '<path d="M3 21V9l6 3V8l6 4V6l6 4v11Z"/><path d="M7 17h.01M11 17h.01M15 17h.01"/>',
  '<rect x="7" y="7" width="10" height="10" rx="1"/><path d="M9 1v3m6-3v3M9 20v3m6-3v3M20 9h3m-3 6h3M1 9h3m-3 6h3M10 10h4v4h-4z"/>'
];

industryCards.forEach((card,index)=>{
  const icon=document.createElement('span');
  icon.className='industry-icon';
  icon.setAttribute('aria-hidden','true');
  icon.innerHTML=`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${industryIconPaths[index]}</svg>`;
  card.prepend(icon);
});
