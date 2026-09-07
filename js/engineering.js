const revealElements=document.querySelectorAll('.reveal');
const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const tapCards=document.querySelectorAll('.cycle-step,.pipeline-node,.standards-grid article,.governance-grid article,.documentation-list span');

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

tapCards.forEach(card=>{
  let tapTimer;
  card.addEventListener('click',()=>{
    if(!window.matchMedia('(hover:none), (pointer:coarse)').matches)return;
    window.clearTimeout(tapTimer);
    card.classList.add('is-tapped');
    tapTimer=window.setTimeout(()=>card.classList.remove('is-tapped'),650);
  });
});
