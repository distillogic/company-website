const industries=[
  {
    slug:'financial-services-banking',
    title:'Financial Services & Banking',
    category:'regulated',
    summary:'Banks, fintech organisations, payment institutions and insurance technology environments.',
    projects:['Customer portals','Internal banking applications','Payment integrations','KYC/AML workflows','Reporting systems','API platforms','Data & analytics','Mobile applications','Workflow automation'],
    note:'Capability is positioned around enterprise applications, integration, data and digital delivery—not specialist core-banking platforms without the required team and references.'
  },
  {
    slug:'insurance',title:'Insurance',category:'regulated',summary:'Digital insurance platforms, operational workflows and customer-facing applications.',
    projects:['Customer & broker portals','Claims management','Policy administration modules','Document processing','Workflow automation','CRM integrations','Reporting','AI document analysis','Mobile applications']
  },
  {
    slug:'energy-utilities',title:'Energy & Utilities',category:'industrial',summary:'Software for energy operations, assets, customers, field teams and data-intensive infrastructure.',
    projects:['Energy management platforms','Asset management systems','Monitoring dashboards','Customer portals','Field service applications','Metering integrations','Data analytics','Predictive maintenance','Renewable energy platforms','BESS / asset monitoring software','Maintenance management applications']
  },
  {
    slug:'telecommunications',title:'Telecommunications',category:'industrial',summary:'Customer, service, operational and integration platforms for telecommunications environments.',
    projects:['Customer self-service portals','Order management','Provisioning integrations','Network operations applications','Billing integrations','CRM integration','Service management','Monitoring','Data analytics','Internal operational platforms']
  },
  {
    slug:'transportation-logistics',title:'Transportation & Logistics',category:'industrial',summary:'Connected transportation, freight, fleet, warehouse and logistics operations.',
    projects:['Transportation Management Systems','Freight platforms','Shipment tracking','Fleet management','Warehouse integrations','Booking systems','Customer portals','Driver applications','Route optimisation','EDI/API integrations','Documentation automation']
  },
  {
    slug:'shipping-maritime',title:'Shipping & Maritime',category:'industrial',summary:'Digital platforms for vessel, cargo, crew, port and maritime operations.',
    projects:['Vessel management platforms','Port integrations','Cargo management','Maritime documentation','Crew applications','Maintenance systems','Fleet monitoring','Operational dashboards','Shipping APIs','Data analytics']
  },
  {
    slug:'manufacturing-industrial',title:'Manufacturing & Industrial',category:'industrial',summary:'Enterprise and operational software supporting production, quality, maintenance and supply networks.',
    projects:['Manufacturing Execution applications','Production monitoring','Quality management','Maintenance systems','Inventory applications','ERP integration','Industrial dashboards','IoT data integration','Predictive maintenance','Supplier portals'],
    note:'The scope does not imply industrial control or PLC engineering without the required specialist capability.'
  },
  {
    slug:'automotive-mobility',title:'Automotive & Mobility',category:'industrial',summary:'Dealer, fleet, mobility, customer and vehicle-data software environments.',
    projects:['Dealer portals','Fleet platforms','Mobility applications','Vehicle data platforms','Customer applications','Maintenance systems','Backend services','API integration','Analytics'],
    note:'The scope does not imply functional-safety or embedded automotive engineering without the required specialist team and qualifications.'
  },
  {
    slug:'retail-commerce',title:'Retail & Commerce',category:'commerce',summary:'Customer, order, inventory, loyalty and marketplace technology for modern commerce.',
    projects:['E-commerce platforms','Customer applications','Order management','Inventory systems','Loyalty platforms','Marketplace integrations','Payment integration','CRM integration','Business intelligence','Personalisation systems']
  },
  {
    slug:'consumer-goods-fmcg',title:'Consumer Goods / FMCG',category:'commerce',summary:'Sales, distribution, inventory and supplier systems for consumer-goods operations.',
    projects:['Sales platforms','Distribution systems','Supplier portals','Inventory applications','Demand forecasting','Reporting','CRM integration','Business intelligence','Workflow automation']
  },
  {
    slug:'travel-tourism-hospitality',title:'Travel, Tourism & Hospitality',category:'commerce',summary:'Reservation, guest, booking, payment and hospitality management applications.',
    projects:['Reservation platforms','Hotel applications','Customer portals','Booking integrations','Payment systems','Loyalty platforms','Mobile applications','Property-management integrations','Revenue dashboards']
  },
  {
    slug:'aviation-airports',title:'Aviation & Airports',category:'commerce',summary:'Passenger, ground-handling, operational and staff-facing airport applications.',
    projects:['Passenger applications','Operational platforms','Ground-handling applications','Staff management','Flight-information integrations','Customer portals','Reporting','Workflow systems'],
    note:'The scope does not imply aviation safety-critical capability without the required qualifications.'
  },
  {
    slug:'healthcare-life-sciences',title:'Healthcare & Life Sciences',category:'regulated',summary:'Patient, clinical workflow, administrative, integration and healthcare data applications.',
    projects:['Patient portals','Appointment systems','Clinical workflow applications','Healthcare integrations','Reporting platforms','Document management','Data platforms','Administrative systems'],
    note:'The scope does not imply certified medical-device software without the required specialist capability and certification.'
  },
  {
    slug:'pharmaceuticals',title:'Pharmaceuticals',category:'regulated',summary:'Regulatory, quality, laboratory, supply-chain and enterprise information systems.',
    projects:['Regulatory workflow systems','Document platforms','Laboratory integrations','Sales applications','Data & analytics','Supply-chain systems','Quality-management applications','Internal enterprise platforms']
  },
  {
    slug:'public-sector-government',title:'Public Sector & Government',category:'regulated',summary:'Digital public services, registries, case management and interoperability platforms.',
    projects:['Citizen portals','Digital public services','Case management','Government workflow systems','Registries','Interoperability platforms','Document management','Data platforms','Public-sector applications','Legacy modernisation']
  },
  {
    slug:'european-institutions',title:'European Institutions & International Organisations',category:'regulated',summary:'Enterprise information systems and digital platforms supporting institutional programmes.',
    projects:['Enterprise information systems','EU digital platforms','Portals','Case-management systems','Data platforms','Integration','Modernisation','Quality engineering','Cloud engineering','Application management']
  },
  {
    slug:'defence-aerospace',title:'Defence & Aerospace',category:'regulated',summary:'Enterprise, logistics, maintenance, data and operational-support software environments.',
    projects:['Enterprise applications','Logistics systems','Data platforms','Maintenance applications','Operational support software','Systems integration','Secure application development'],
    note:'The scope does not claim classified or weapon-system expertise, or certifications that have not been established for an engagement.'
  },
  {
    slug:'construction-engineering',title:'Construction & Engineering',category:'industrial',summary:'Project, procurement, asset, document and field applications for engineering organisations.',
    projects:['Project management systems','Contractor portals','Procurement platforms','Document management','Asset management','Field applications','Reporting','ERP integrations','Workforce applications']
  },
  {
    slug:'real-estate-property',title:'Real Estate & Property',category:'industrial',summary:'Property, tenant, facility, maintenance and marketplace platforms.',
    projects:['Property management platforms','Tenant portals','Facility management','CRM systems','Property marketplaces','Maintenance applications','Document automation','Analytics']
  },
  {
    slug:'infrastructure',title:'Infrastructure',category:'industrial',summary:'Asset, maintenance, monitoring and contractor platforms for major infrastructure programmes.',
    projects:['Asset management','Maintenance management','Field applications','Monitoring','Project platforms','Contractor portals','Data analytics','Reporting','Integration']
  },
  {
    slug:'agriculture-agritech',title:'Agriculture & AgriTech',category:'industrial',summary:'Farm, traceability, production, marketplace and agricultural data platforms.',
    projects:['Farm management platforms','Traceability','Supply-chain applications','IoT data platforms','Production monitoring','Marketplace platforms','Mobile applications','Analytics']
  },
  {
    slug:'food-beverage',title:'Food & Beverage',category:'industrial',summary:'Traceability, production, inventory, distribution and quality applications.',
    projects:['Traceability systems','Production applications','Inventory','Distribution','Quality management','Supplier portals','ERP integration','Sales applications']
  },
  {
    slug:'technology-software-companies',title:'Technology & Software Companies',category:'digital',summary:'Engineering delivery and subcontracting capability for product and software organisations.',
    projects:['Product engineering','Feature development','Module delivery','Backend development','Frontend development','Quality engineering','DevOps','Data engineering','Application modernisation','Engineering squads']
  },
  {
    slug:'saas-digital-platforms',title:'SaaS & Digital Platforms',category:'digital',summary:'Product engineering for multi-tenant cloud software and digital platform businesses.',
    projects:['SaaS product development','Multi-tenant platforms','APIs','Subscription systems','Admin platforms','Cloud infrastructure','Data analytics','Product modernisation','QA automation']
  },
  {
    slug:'professional-services',title:'Professional Services',category:'commerce',summary:'Client, workflow, document and knowledge platforms for professional organisations.',
    projects:['Client portals','Workflow systems','Document management','CRM','Reporting','Automation','AI knowledge systems','Internal applications']
  },
  {
    slug:'media-entertainment',title:'Media & Entertainment',category:'commerce',summary:'Content, subscription, customer and data platforms for digital media businesses.',
    projects:['Digital content platforms','Subscription platforms','Streaming backends','Content management','Customer applications','Data analytics','Recommendation systems','Advertising integrations']
  },
  {
    slug:'education-edtech',title:'Education & EdTech',category:'commerce',summary:'Learning, student, examination and administration platforms.',
    projects:['Learning platforms','Student portals','Learning management systems','Examination systems','Administration platforms','Mobile learning','Reporting','AI learning assistants']
  },
  {
    slug:'gaming-interactive-platforms',title:'Gaming & Interactive Platforms',category:'digital',summary:'Online, account, backend, matchmaking and administration platform capabilities.',
    projects:['Online platforms','Player/account systems','Backend services','Matchmaking systems','Payment integration','Analytics','Administration platforms','APIs']
  },
  {
    slug:'sports-events',title:'Sports & Events',category:'commerce',summary:'Event, registration, ticketing, tournament and member-facing applications.',
    projects:['Event management systems','Registration platforms','Ticketing','Athlete/member portals','Tournament platforms','Payment integration','Mobile applications','Analytics']
  },
  {
    slug:'security-technology-operations',title:'Security & Technology Operations',category:'regulated',summary:'Security-enabled applications, identity integrations, monitoring and audit workflows.',
    projects:['Security-enabled application development','Identity & access integrations','Monitoring platforms','Audit systems','Security workflow applications'],
    note:'The scope does not position DISTILLOGIC as a SOC or penetration-testing provider without the required specialists.'
  }
];

const categoryLabels={
  regulated:'Regulated & Public',
  industrial:'Industrial & Infrastructure',
  commerce:'Commerce & Services',
  digital:'Technology & Digital'
};

const iconPaths={
  regulated:'<path d="M4 20h16M6 17h12M7 9v8M12 9v8M17 9v8M4 7l8-4 8 4H4Z"/>',
  industrial:'<path d="M4 20V9l6 3V8l5 3V5h5v15H4Z"/><path d="M8 16h1M12 16h1M16 16h1"/>',
  commerce:'<path d="M5 8h14l-1 11H6L5 8Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/>',
  digital:'<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M9 9h6v6H9zM12 1v3M12 20v3M1 12h3M20 12h3"/>'
};

const grid=document.querySelector('[data-industry-grid]');
const detail=document.querySelector('[data-industry-detail]');
const detailShell=document.querySelector('[data-detail-shell]');
const directoryLayout=document.querySelector('[data-directory-layout]');
const searchInput=document.querySelector('[data-industry-search]');
const filterButtons=[...document.querySelectorAll('[data-category]')];
const filterSelect=document.querySelector('[data-industry-filter-select]');
const resultCount=document.querySelector('[data-result-count]');
const emptyState=document.querySelector('[data-empty-state]');
const mobileDirectory=window.matchMedia('(max-width: 800px)');
let activeCategory='all';
let activeSlug=location.hash.slice(1);

if(!industries.some(industry=>industry.slug===activeSlug))activeSlug=industries[0].slug;

const normalise=value=>value.toLowerCase().replace(/[^a-z0-9]+/g,' ').trim();

const industryIcon=category=>`<svg aria-hidden="true" viewBox="0 0 24 24">${iconPaths[category]}</svg>`;

const getVisibleIndustries=()=>{
  const query=normalise(searchInput?.value||'');
  return industries.filter(industry=>{
    const matchesCategory=activeCategory==='all'||industry.category===activeCategory;
    const haystack=normalise([industry.title,industry.summary,...industry.projects].join(' '));
    return matchesCategory&&(!query||haystack.includes(query));
  });
};

const renderDetail=industry=>{
  if(!detail||!industry)return;
  detail.innerHTML=`
    <div class="detail-topline">
      <p class="detail-category">${categoryLabels[industry.category]}</p>
      <span class="detail-index">${String(industries.indexOf(industry)+1).padStart(2,'0')}</span>
    </div>
    <h3>${industry.title}</h3>
    <p class="detail-summary">${industry.summary}</p>
    <p class="detail-label">Typical Projects</p>
    <ul class="detail-projects">${industry.projects.map(project=>`<li>${project}</li>`).join('')}</ul>
    ${industry.note?`<p class="scope-note"><strong>Scope note</strong>${industry.note}</p>`:''}
  `;
};

const positionDetail=()=>{
  if(!detailShell||!directoryLayout)return;
  const activeCard=grid?.querySelector(`[data-industry="${activeSlug}"]`);
  if(mobileDirectory.matches&&activeCard){
    activeCard.insertAdjacentElement('afterend',detailShell);
  }else if(detailShell.parentElement!==directoryLayout){
    directoryLayout.append(detailShell);
  }
};

const selectIndustry=(slug,{updateHash=true,focusDetail=false}={})=>{
  const industry=industries.find(item=>item.slug===slug);
  if(!industry)return;
  activeSlug=slug;
  grid?.querySelectorAll('[data-industry]').forEach(card=>{
    const selected=card.dataset.industry===slug;
    card.classList.toggle('is-active',selected);
    card.setAttribute('aria-pressed',String(selected));
  });
  renderDetail(industry);
  positionDetail();
  if(updateHash)history.replaceState(null,'',`#${slug}`);
  if(focusDetail&&mobileDirectory.matches)detailShell?.scrollIntoView({behavior:'smooth',block:'nearest'});
};

const renderDirectory=()=>{
  if(!grid)return;
  const visible=getVisibleIndustries();
  resultCount.textContent=`${visible.length} ${visible.length===1?'industry':'industries'}`;
  emptyState.hidden=visible.length!==0;
  grid.innerHTML=visible.map(industry=>`
    <button class="industry-card" type="button" data-industry="${industry.slug}" aria-pressed="${industry.slug===activeSlug}">
      <span class="industry-card-icon">${industryIcon(industry.category)}</span>
      <span class="industry-card-title">${industry.title}</span>
      <span class="industry-card-chevron" aria-hidden="true">›</span>
    </button>
  `).join('');
  if(!visible.some(industry=>industry.slug===activeSlug)&&visible[0])activeSlug=visible[0].slug;
  if(visible.length)selectIndustry(activeSlug,{updateHash:false});
  else if(detailShell)detailShell.remove();
};

grid?.addEventListener('click',event=>{
  const card=event.target.closest('[data-industry]');
  if(!card)return;
  selectIndustry(card.dataset.industry,{focusDetail:true});
});

filterButtons.forEach(button=>button.addEventListener('click',()=>{
  activeCategory=button.dataset.category;
  if(filterSelect)filterSelect.value=activeCategory;
  filterButtons.forEach(item=>{
    const active=item===button;
    item.classList.toggle('is-active',active);
    item.setAttribute('aria-pressed',String(active));
  });
  renderDirectory();
}));

filterSelect?.addEventListener('change',()=>{
  activeCategory=filterSelect.value;
  filterButtons.forEach(button=>{
    const active=button.dataset.category===activeCategory;
    button.classList.toggle('is-active',active);
    button.setAttribute('aria-pressed',String(active));
  });
  renderDirectory();
});

searchInput?.addEventListener('input',renderDirectory);
mobileDirectory.addEventListener('change',positionDetail);
window.addEventListener('hashchange',()=>{
  const slug=location.hash.slice(1);
  if(industries.some(industry=>industry.slug===slug))selectIndustry(slug,{updateHash:false});
});

renderDirectory();

const revealObserver=new IntersectionObserver(entries=>{
  entries.forEach(entry=>{
    if(entry.isIntersecting){
      entry.target.classList.add('visible');
      revealObserver.unobserve(entry.target);
    }
  });
},{threshold:.08});

document.querySelectorAll('.reveal').forEach(element=>revealObserver.observe(element));
