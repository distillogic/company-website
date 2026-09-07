const greekPrivacySectionIds=[
  'data-controller-el',
  'data-collected-el',
  'data-use-el',
  'legal-basis-el',
  'talent-pool-el',
  'sharing-el',
  'transfers-el',
  'retention-el',
  'rights-el',
  'security-el',
  'cookies-el',
  'third-party-el',
  'children-el',
  'automated-decisions-el',
  'policy-changes-el',
  'contact-el'
];

document.querySelectorAll('.privacy-article[data-legal-language="el"] section').forEach((section,index)=>{
  section.id=greekPrivacySectionIds[index];
});

const greekOtherInformation=document.getElementById('third-party-el')?.parentElement;
if(greekOtherInformation)greekOtherInformation.id='other-information-el';
