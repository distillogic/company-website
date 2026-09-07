const assignmentItems=[
  {
    title:'Software Development',
    items:['New software applications','Enterprise applications','Customer and supplier portals','Internal business systems','Backend and frontend applications','Mobile applications','APIs and microservices','New features','Application modules']
  },
  {
    title:'Systems Integration',
    items:['API integration','ERP integration','CRM integration','Payment integrations','Identity and SSO','Third-party platforms','Middleware','Enterprise data exchange','Event-driven integrations']
  },
  {
    title:'Application Modernisation',
    items:['Legacy modernisation','Framework migration','Architecture modernisation','Monolith transformation','UI modernisation','Database modernisation','Technical debt reduction','Cloud readiness']
  },
  {
    title:'Cloud & DevOps',
    items:['Cloud architecture','Cloud migration','CI/CD','Docker','Kubernetes','Infrastructure as Code','Environment setup','Deployment automation','Release engineering']
  },
  {
    title:'Quality Engineering',
    items:['Manual QA','Test automation','API testing','Integration testing','End-to-end testing','Performance testing','Regression testing','Release validation']
  },
  {
    title:'Data Engineering',
    items:['ETL and ELT','Data pipelines','Data integration','Data warehouses','Data migration','Analytics','Business intelligence','Reporting']
  },
  {
    title:'AI Engineering',
    items:['LLM integration','Retrieval-augmented generation','AI agents','Enterprise assistants','Document intelligence','NLP','Machine learning','Intelligent automation']
  },
  {
    title:'Application Support',
    items:['L1, L2 and L3 support','Application maintenance','Incident resolution','Production investigation','Release support','Technology upgrades','Continuous enhancement']
  },
  {
    title:'Architecture & Analysis',
    items:['Solution architecture','Technical architecture','Business analysis','Requirements analysis','System design','Technical assessment','Proof of concept','MVP and prototype delivery']
  },
  {
    title:'Delivery & Project Support',
    items:['Project management','Delivery management','Release management','Business analysis','Technical documentation','Knowledge transfer','Vendor transition support']
  }
];

const carousel=document.querySelector('[data-assignment-carousel]');
const titleElement=document.querySelector('[data-assignment-title]');
const copyElement=document.querySelector('[data-assignment-copy]');
const previousButton=document.querySelector('[data-assignment-prev]');
const nextButton=document.querySelector('[data-assignment-next]');
let activeAssignment=0;

const showAssignment=index=>{
  activeAssignment=(index+assignmentItems.length)%assignmentItems.length;
  carousel?.classList.add('is-changing');
  window.setTimeout(()=>{
    const item=assignmentItems[activeAssignment];
    if(titleElement)titleElement.textContent=item.title;
    if(copyElement){
      copyElement.replaceChildren(...item.items.map(text=>{
        const listItem=document.createElement('li');
        listItem.textContent=text;
        return listItem;
      }));
    }
    carousel?.classList.remove('is-changing');
  },140);
};

previousButton?.addEventListener('click',()=>showAssignment(activeAssignment-1));
nextButton?.addEventListener('click',()=>showAssignment(activeAssignment+1));

carousel?.addEventListener('keydown',event=>{
  if(event.key==='ArrowLeft')showAssignment(activeAssignment-1);
  if(event.key==='ArrowRight')showAssignment(activeAssignment+1);
});

const revealElements=document.querySelectorAll('.reveal');
const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if(reducedMotion||!('IntersectionObserver' in window)){
  revealElements.forEach(element=>element.classList.add('visible'));
}else{
  const revealObserver=new IntersectionObserver(entries=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  },{threshold:.08});

  revealElements.forEach(element=>revealObserver.observe(element));
}
