const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const revealItems = document.querySelectorAll('.reveal');

if (reducedMotion || !('IntersectionObserver' in window)) {
  revealItems.forEach((item) => item.classList.add('is-visible'));
} else {
  const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return;
      }

      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, {
    threshold:0.12,
    rootMargin:'0px 0px -40px'
  });

  revealItems.forEach((item) => revealObserver.observe(item));
}

const interactiveCards = document.querySelectorAll('.interactive-card');

const activateCard = (card) => {
  interactiveCards.forEach((item) => {
    if (item !== card) {
      item.classList.remove('is-active');
    }
  });

  card.classList.toggle('is-active');
};

interactiveCards.forEach((card) => {
  card.addEventListener('click', () => activateCard(card));
  card.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    event.preventDefault();
    activateCard(card);
  });
});

const profileForm = document.querySelector('[data-profile-form]');

if (profileForm && Array.isArray(window.DISTILLOGIC_PROFILES)) {
  const pillarSelect = profileForm.querySelector('[data-profile-pillar]');
  const roleSelect = profileForm.querySelector('[data-profile-role]');
  const senioritySelect = profileForm.querySelector('[data-profile-seniority]');
  const results = document.querySelector('[data-profile-results]');
  const status = document.querySelector('[data-profile-status]');

  const rolesByPillar = {
    'ai-data': [
      ['ai-ml', 'AI / Machine Learning Engineers'],
      ['data-engineer', 'Data Engineers'],
      ['data-analyst', 'Data Analysts']
    ],
    'core-software': [
      ['full-stack', 'Full-Stack Developers'],
      ['backend', 'Backend Developers'],
      ['frontend', 'Frontend Developers'],
      ['mobile', 'Mobile Developers']
    ],
    'infrastructure-security': [
      ['devops-cloud', 'DevOps / Cloud Engineers'],
      ['cybersecurity', 'Cybersecurity Experts'],
      ['qa-testing', 'QA / Software Testing'],
      ['it-leadership', 'IT Leadership & Governance'],
      ['it-business-analysis', 'Business Analysis & IT Project Delivery'],
      ['it-operations', 'IT Support & Application Operations']
    ],
    'embedded-debugging': [
      ['embedded-engineering', 'Embedded Software & Hardware Engineers'],
      ['embedded-validation', 'Embedded Test & Validation Engineers'],
      ['linux-iot', 'Linux, IoT & Edge Engineers'],
      ['vr-xr', 'VR / XR Systems Engineers'],
      ['debugging-qa', 'Debugging & Systems QA Engineers']
    ],
    'management-design': [
      ['project-management', 'Project Managers / Scrum Masters'],
      ['ui-ux', 'UI / UX Designers']
    ]
  };

  const clearResults = (message = 'Select all three filters and press Search Profiles to view matching profiles.') => {
    results.replaceChildren();
    results.hidden = true;
    status.textContent = message;
    status.classList.remove('has-results');
  };

  const addText = (parent, tag, className, text) => {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = text;
    parent.append(element);
    return element;
  };

  const localiseProfile = (profile) => {
    if (document.documentElement.lang !== 'el' || !window.DISTILLOGIC_PROFILES_EL?.[profile.id]) {
      return profile;
    }

    const translation = window.DISTILLOGIC_PROFILES_EL[profile.id];
    const translatedSkills = {};
    Object.values(profile.skills).forEach((value, index) => {
      translatedSkills[translation.skillLabels?.[index] || Object.keys(profile.skills)[index]] = translation.skillValues?.[index] || value;
    });

    return {
      ...profile,
      ...translation,
      skills: translatedSkills,
      projects: (profile.projects || []).map((project, index) => ({
        ...project,
        ...(translation.projects?.[index] || {})
      }))
    };
  };

  const createProfileCard = (sourceProfile) => {
    const profile = localiseProfile(sourceProfile);
    const matchedPlacement = profile.placements.find((placement) => (
      placement.pillar === pillarSelect.value &&
      placement.role === roleSelect.value &&
      placement.seniority === senioritySelect.value
    ));
    const seniority = matchedPlacement?.seniority || profile.placements[0].seniority;
    const card = document.createElement('article');
    card.className = 'profile-card profile-directory-card';

    const header = document.createElement('div');
    header.className = 'profile-card-header';
    const identity = document.createElement('div');
    addText(identity, 'span', 'profile-id', `#${profile.id}`);
    addText(identity, 'h3', '', profile.name);
    addText(identity, 'p', 'profile-role', profile.title);
    header.append(identity);
    addText(header, 'span', `profile-level profile-level-${seniority}`, seniority === 'senior' ? 'Senior / Lead' : seniority === 'mid' ? 'Mid-Level' : 'Junior');
    card.append(header);

    addText(card, 'p', 'profile-experience', profile.level);
    addText(card, 'p', 'profile-location', profile.location);
    addText(card, 'p', 'profile-summary', profile.summary);

    const skillGrid = document.createElement('div');
    skillGrid.className = 'profile-skills-grid';
    Object.entries(profile.skills).forEach(([label, value]) => {
      const skill = document.createElement('div');
      addText(skill, 'h4', '', label);
      addText(skill, 'p', 'profile-tech', value);
      skillGrid.append(skill);
    });
    card.append(skillGrid);

    if (profile.projects?.length) {
      addText(card, 'h4', 'profile-projects-heading', 'Selected Relevant Projects');
      const projectList = document.createElement('div');
      projectList.className = 'profile-project-list';
      profile.projects.forEach((project, projectIndex) => {
        const projectCard = document.createElement('section');
        addText(projectCard, 'span', 'profile-industry', project.industry);
        addText(projectCard, 'h5', '', project.name);
        const organisationDirectory = document.documentElement.lang === 'el'
          ? window.DISTILLOGIC_PROFILE_ORGANISATIONS_EL
          : window.DISTILLOGIC_PROFILE_ORGANISATIONS;
        const organisation = organisationDirectory?.[profile.id]?.[projectIndex];
        if (organisation) {
          addText(
            projectCard,
            'p',
            'profile-prior-organisation',
            `${document.documentElement.lang === 'el' ? 'Ατομική προηγούμενη εμπειρία' : 'Individual prior experience'} · ${organisation}`
          );
        }
        addText(projectCard, 'p', '', project.detail);
        projectList.append(projectCard);
      });
      card.append(projectList);
    }

    const verification = document.createElement('div');
    verification.className = 'profile-verification';
    addText(verification, 'strong', '', 'Credentials');
    addText(verification, 'span', '', 'Published after documentary verification.');
    card.append(verification);
    return card;
  };

  const populateRoles = () => {
    roleSelect.replaceChildren();
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = pillarSelect.value ? 'Select a specialisation' : 'Select a pillar first';
    roleSelect.append(placeholder);
    (rolesByPillar[pillarSelect.value] || []).forEach(([value, label]) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = label;
      roleSelect.append(option);
    });
    roleSelect.disabled = !pillarSelect.value;
  };

  pillarSelect.addEventListener('change', () => {
    populateRoles();
    clearResults('Filters changed. Press Search Profiles to view updated results.');
  });
  roleSelect.addEventListener('change', () => clearResults('Filters changed. Press Search Profiles to view updated results.'));
  senioritySelect.addEventListener('change', () => clearResults('Filters changed. Press Search Profiles to view updated results.'));

  const renderMatches = () => {
    if (!pillarSelect.value || !roleSelect.value || !senioritySelect.value) {
      clearResults('Please select a pillar, specialisation and seniority before searching.');
      return false;
    }

    const matches = window.DISTILLOGIC_PROFILES
      .filter((profile) => profile.placements.some((placement) => (
        placement.pillar === pillarSelect.value &&
        placement.role === roleSelect.value &&
        placement.seniority === senioritySelect.value
      )))
      .sort((profileA, profileB) => {
        const featuredProfileId = 'AI-OPT-SR-075';
        return Number(profileB.id === featuredProfileId) - Number(profileA.id === featuredProfileId);
      });

    results.replaceChildren(...matches.map(createProfileCard));
    results.hidden = matches.length === 0;
    status.textContent = matches.length
      ? `${matches.length} matching ${matches.length === 1 ? 'profile' : 'profiles'}`
      : 'No profiles currently match these filters. Try another combination.';
    status.classList.toggle('has-results', matches.length > 0);
    return true;
  };

  profileForm.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!renderMatches()) {
      profileForm.reportValidity();
    }
  });

  profileForm.addEventListener('reset', () => {
    window.setTimeout(() => {
      populateRoles();
      clearResults();
    }, 0);
  });

  new MutationObserver(() => {
    if (!results.hidden && pillarSelect.value && roleSelect.value && senioritySelect.value) {
      renderMatches();
    }
  }).observe(document.documentElement, {attributes:true, attributeFilter:['lang']});
}
