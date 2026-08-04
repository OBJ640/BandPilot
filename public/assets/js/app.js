const API_ROOT = '/api/v1';

const fallbackSongs = [
  { id: 1, title: 'Little Wing', artist: 'Jimi Hendrix', progress_level: 'rehearsing', status: 'practising', problem_notes: 'Chorus entry and timing' },
  { id: 2, title: 'Everlong', artist: 'Foo Fighters', progress_level: 'polishing', status: 'practising', problem_notes: 'Bridge dynamics' },
  { id: 3, title: 'Dreams', artist: 'Fleetwood Mac', progress_level: 'polishing', status: 'practising', problem_notes: 'Full-band balance' },
  { id: 4, title: 'Yellow', artist: 'Coldplay', progress_level: 'ready', status: 'ready', problem_notes: 'Final run-through' },
  { id: 5, title: '505', artist: 'Arctic Monkeys', progress_level: 'rehearsing', status: 'learning', problem_notes: 'Ending transition' },
  { id: 6, title: 'Creep', artist: 'Radiohead', progress_level: 'ready', status: 'ready', problem_notes: 'Performance ready' }
];

const songProgressLevels = [
  ['starting', 'Just starting'],
  ['learning', 'Learning parts'],
  ['rehearsing', 'Can rehearse together'],
  ['polishing', 'Almost finished'],
  ['ready', 'Performance ready']
];

const bandRoleOptions = [
  'Lead vocals', 'Backing vocals',
  'Guitar', 'Lead guitar', 'Rhythm guitar', 'Acoustic guitar',
  'Bass', 'Drums', 'Percussion',
  'Piano', 'Keyboards', 'Synthesizer', 'DJ / Electronic',
  'Violin', 'Viola', 'Cello', 'Double bass',
  'Flute', 'Clarinet', 'Saxophone',
  'Trumpet', 'Trombone', 'French horn',
  'Harmonica', 'Ukulele', 'Banjo',
  'Producer', 'Songwriter', 'Composer / Arranger', 'Music director',
  'Sound engineer', 'Band manager', 'Multi-instrumentalist', 'Other'
];

const pageTitles = {
  overview: 'Good morning, Ricky.',
  songs: 'Songs',
  rehearsals: 'Rehearsals',
  performances: 'Performances',
  assistant: 'AI Assistant'
};

const aiResults = {
  rehearsal: {
    type: 'Draft rehearsal plan',
    title: 'Saturday · 120 minutes',
    items: [
      ['15 min', 'Warm-up together', 'Focus on shared tempo and clear count-ins.'],
      ['30 min', 'Little Wing chorus', 'Start slowly, then practise the entry five times.'],
      ['25 min', 'Everlong bridge', 'Work on dynamics before playing the full song.'],
      ['35 min', 'Full set run', 'Play without stopping and record new feedback.'],
      ['15 min', 'Review and next steps', 'Update song progress before leaving.']
    ]
  },
  summary: {
    type: 'Recent problem summary',
    title: 'Three patterns need attention',
    items: [
      ['3 times', 'Chorus timing', 'The band entered the Little Wing chorus at different times.'],
      ['2 times', 'Bridge dynamics', 'Everlong became too loud before the final chorus.'],
      ['2 times', 'Instrument balance', 'Vocals were difficult to hear during full-band runs.']
    ]
  },
  performance: {
    type: 'Performance readiness check',
    title: 'Band readiness · Preparing well',
    items: [
      ['Ready', 'Creep and Yellow', 'These songs only need a final full-set run.'],
      ['Next', 'Dreams and Everlong', 'Practise transitions without stopping.'],
      ['Priority', 'Little Wing', 'Fix the chorus entry before the next full run.']
    ]
  }
};

let songs = [];
let rehearsals = [];
let members = [];
let reviewHistory = [];
let bands = [];
let currentBand = null;
let currentUser = null;
let currentQuestionnaire = { answers: null, completion_percent: 0, completed: false };
let currentSongFilter = 'all';
let apiOnline = false;
let editingPlan = false;
let csrfToken = '';
let questionnaireStep = 1;
let activeRehearsalReview = null;
let activeAvailability = null;
let currentRehearsalFilter = 'all';

function initializeBandRoleSelects() {
  const options = `<option value="">Choose a band role</option>${bandRoleOptions.map((role) => `<option value="${safeText(role)}">${safeText(role)}</option>`).join('')}`;
  document.querySelectorAll('[data-band-role-select]').forEach((select) => { select.innerHTML = options; });
}

function safeText(value) {
  const element = document.createElement('span');
  element.textContent = value ?? '';
  return element.innerHTML;
}

function initials(value) {
  const parts = String(value || '').trim().split(/\s+/).filter(Boolean);
  return (parts.slice(0, 2).map((part) => part[0]).join('') || 'BP').toUpperCase();
}

function updateBandUI() {
  const name = currentBand?.name || 'Set up your band';
  document.getElementById('bandAvatarDisplay').textContent = initials(name);
  document.getElementById('bandNameDisplay').textContent = name;
  document.getElementById('bandDescriptionDisplay').textContent = currentBand
    ? currentBand.description || `${currentBand.member_count || 1} member${Number(currentBand.member_count) === 1 ? '' : 's'}`
    : 'Complete the questionnaire';
  updateProfileMeta();
}

function updateUserUI() {
  if (!currentUser) return;
  document.getElementById('profileAvatarDisplay').textContent = initials(currentUser.name);
  document.getElementById('profileNameDisplay').textContent = currentUser.name;
  updateProfileMeta();
  pageTitles.overview = `Good morning, ${currentUser.name}.`;
  if (window.location.hash === '' || window.location.hash === '#overview') {
    document.getElementById('pageTitle').textContent = pageTitles.overview;
  }
}

function updateProfileMeta() {
  const role = currentBand?.user_role === 'owner' ? 'Owner' : currentBand ? 'Member' : 'No band yet';
  const instrument = String(currentUser?.instrument || '').trim();
  document.getElementById('profileRoleDisplay').textContent = instrument ? `${instrument} · ${role}` : role;
}

function showView(name, updateHash = true) {
  if (!pageTitles[name]) return;
  document.querySelectorAll('.view').forEach((view) => {
    view.classList.toggle('is-active', view.dataset.page === name);
  });
  document.querySelectorAll('.nav-item').forEach((item) => {
    item.classList.toggle('is-active', item.dataset.view === name);
  });
  document.getElementById('pageTitle').textContent = pageTitles[name];
  document.getElementById('sidebar').classList.remove('is-open');
  document.getElementById('menuButton').setAttribute('aria-expanded', 'false');
  if (updateHash && window.location.hash !== `#${name}`) window.location.hash = name;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function songColor(index) {
  const colors = [
    ['#e5e0ff', '#7866ff'], ['#ffe2d1', '#ff8d4d'], ['#dff1c5', '#6f9d34'],
    ['#cfe9ff', '#3d8cc9'], ['#f7d8e5', '#c15c87'], ['#f5e3aa', '#b98918']
  ];
  return colors[index % colors.length];
}

function songProgressInfo(level) {
  const index = Math.max(0, songProgressLevels.findIndex(([value]) => value === level));
  return { index, value: songProgressLevels[index][0], label: songProgressLevels[index][1] };
}

function songProgressPips(level) {
  const activeIndex = songProgressInfo(level).index;
  return songProgressLevels.map((_, index) => `<i${index <= activeIndex ? ' class="is-filled"' : ''}></i>`).join('');
}

function filteredSongs() {
  if (currentSongFilter === 'ready') return songs.filter((song) => song.status === 'ready');
  if (currentSongFilter === 'needs-work') return songs.filter((song) => song.status !== 'ready' || songProgressInfo(song.progress_level).index < 4);
  return songs;
}

function renderSongs() {
  const grid = document.getElementById('songGrid');
  const visibleSongs = filteredSongs();
  if (!visibleSongs.length) {
    grid.innerHTML = '<div class="empty-state"><strong>No songs in this group</strong><p>Try another filter or add a new song.</p></div>';
    return;
  }

  grid.innerHTML = visibleSongs.map((song, index) => {
    const [soft, strong] = songColor(index);
    const progress = songProgressInfo(song.progress_level);
    const status = String(song.status || 'learning').replace(/^./, (letter) => letter.toUpperCase());
    return `
      <article class="song-card" style="--song-color:${soft};--song-bar:${strong}">
        <span class="status-pill">${safeText(status)}</span>
        ${currentBand?.user_role === 'owner' ? `<button class="song-edit-button" type="button" data-song-edit="${Number(song.id)}" aria-label="Edit ${safeText(song.title)}">Edit</button>` : ''}
        <h3>${safeText(song.title)}</h3>
        <p>${safeText(song.artist || 'Unknown artist')}</p>
        <div class="song-bottom">
          <div><span>${safeText(song.problem_notes || 'No current problems')}</span><strong>${safeText(progress.label)}</strong></div>
          <div class="song-progress-level" aria-label="${safeText(progress.label)}">${songProgressPips(progress.value)}</div>
        </div>
      </article>`;
  }).join('');
  grid.querySelectorAll('[data-song-edit]').forEach((button) => {
    button.addEventListener('click', () => openSongForm('edit', Number(button.dataset.songEdit)));
  });
}

async function request(path, options = {}) {
  const { skipAuthRedirect = false, headers: extraHeaders = {}, ...fetchOptions } = options;
  const method = String(fetchOptions.method || 'GET').toUpperCase();
  let response;
  try {
    response = await fetch(`${API_ROOT}${path}`, {
      headers: {
        'Content-Type': 'application/json',
        ...(csrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method) ? { 'X-CSRF-Token': csrfToken } : {}),
        ...extraHeaders
      },
      ...fetchOptions
    });
  } catch {
    setApiState(false);
    throw new Error('Cannot connect to the BandPilot server.');
  }
  const data = await response.json().catch(() => ({}));
  if (response.status === 401 && !skipAuthRedirect) {
    resetAppState();
    showAuthScreen('Your session ended. Please sign in again.');
  }
  if (!response.ok) throw new Error(data.error || `Request failed (${response.status})`);
  return data;
}

function setApiState(isOnline) {
  apiOnline = isOnline;
  const status = document.getElementById('apiStatus');
  status.classList.toggle('is-online', isOnline);
  status.classList.toggle('is-offline', !isOnline);
  status.lastChild.textContent = isOnline ? ' System online' : ' Preview mode';
}

async function checkApi() {
  try {
    await request('/health');
    setApiState(true);
  } catch {
    setApiState(false);
  }
}

function showAuthScreen(message = '') {
  document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
  document.getElementById('appShell').classList.add('is-hidden');
  document.getElementById('authScreen').classList.remove('is-hidden');
  if (message) document.getElementById('loginFormMessage').textContent = message;
}

function showAppShell() {
  document.getElementById('authScreen').classList.add('is-hidden');
  document.getElementById('appShell').classList.remove('is-hidden');
}

function setAuthMode(mode) {
  const isRegister = mode === 'register';
  document.getElementById('loginForm').classList.toggle('is-hidden', isRegister);
  document.getElementById('registerForm').classList.toggle('is-hidden', !isRegister);
  document.getElementById('authTitle').textContent = isRegister ? 'Create your account' : 'Sign in to your band';
  document.querySelectorAll('[data-auth-mode]').forEach((button) => {
    const selected = button.dataset.authMode === mode;
    button.classList.toggle('is-active', selected);
    button.setAttribute('aria-selected', String(selected));
  });
  document.getElementById('loginFormMessage').textContent = '';
  document.getElementById('registerFormMessage').textContent = '';
}

function resetAppState() {
  songs = [];
  rehearsals = [];
  members = [];
  reviewHistory = [];
  bands = [];
  currentBand = null;
  currentUser = null;
  currentQuestionnaire = { answers: null, completion_percent: 0, completed: false };
  csrfToken = '';
  renderSongs();
  renderRehearsals();
  updateQuestionnaireCard();
}

async function beginAuthenticatedSession(data) {
  if (!data.user) throw new Error('The server did not return the signed-in user.');
  currentUser = data.user;
  csrfToken = data.csrf_token || csrfToken;
  showAppShell();
  updateUserUI();
  await initializeBandData();
}

async function login(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const input = formDataObject(form);
  const message = document.getElementById('loginFormMessage');
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Sign in';
  message.textContent = '';
  setSubmitting(form, true);
  try {
    if (!apiOnline) throw new Error('Start the BandPilot PHP server before signing in.');
    const data = await request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: input.email, password: input.password }),
      skipAuthRedirect: true
    });
    await beginAuthenticatedSession(data);
    form.reset();
    showToast('Welcome back.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

async function register(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const input = formDataObject(form);
  const message = document.getElementById('registerFormMessage');
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Create account';
  message.textContent = '';
  if (input.password !== input.password_confirmation) {
    message.textContent = 'The two passwords do not match.';
    return;
  }
  setSubmitting(form, true);
  try {
    if (!apiOnline) throw new Error('Start the BandPilot PHP server before creating an account.');
    const data = await request('/auth/register', {
      method: 'POST',
      body: JSON.stringify({ name: input.name, email: input.email, password: input.password }),
      skipAuthRedirect: true
    });
    await beginAuthenticatedSession(data);
    form.reset();
    showToast('Account created. Let’s set up your first band.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

async function logout() {
  const button = document.getElementById('logoutButton');
  button.disabled = true;
  try {
    if (!apiOnline) throw new Error('Cannot sign out while the BandPilot server is offline.');
    await request('/auth/logout', { method: 'POST' });
    resetAppState();
    setAuthMode('login');
    showAuthScreen('You have signed out.');
  } catch (error) {
    showToast(error.message);
  } finally {
    button.disabled = false;
  }
}

function userStorageKey(name, includeBand = true) {
  const userId = currentUser?.id || 'guest';
  const bandId = includeBand ? `-${currentBand?.id || 'none'}` : '';
  return `bandpilot-${name}-${userId}${bandId}`;
}

function ensureCurrentBand() {
  if (currentBand) return true;
  openQuestionnaire();
  showToast('Set up your first band to continue.');
  return false;
}

async function loadSongs() {
  if (!currentBand) {
    songs = [];
    renderSongs();
    return;
  }
  if (!apiOnline) {
    songs = [];
    renderSongs();
    return;
  }
  try {
    const data = await request(`/bands/${currentBand.id}/songs`);
    if (!Array.isArray(data.songs)) throw new Error('The server returned invalid song data.');
    songs = data.songs;
  } catch (error) {
    showToast(error.message);
  }
  renderSongs();
}

function renderRehearsals() {
  const timeline = document.getElementById('rehearsalTimeline');
  if (!timeline) return;
  if (!currentBand) {
    timeline.innerHTML = '<div class="empty-state"><strong>No band selected</strong><p>Set up a band before planning rehearsals.</p></div>';
    return;
  }
  const visibleRehearsals = rehearsals.filter((rehearsal) => {
    const isPast = new Date(rehearsal.start_time).getTime() <= Date.now();
    if (currentRehearsalFilter === 'upcoming') return rehearsal.status === 'planned' && !isPast;
    if (currentRehearsalFilter === 'completed') return rehearsal.status === 'completed';
    if (currentRehearsalFilter === 'reviewed') return Number(rehearsal.review_completed) === 1;
    if (currentRehearsalFilter === 'cancelled') return rehearsal.status === 'cancelled';
    return true;
  });
  if (!visibleRehearsals.length) {
    timeline.innerHTML = '<div class="empty-state"><strong>No rehearsals yet</strong><p>Plan your first rehearsal, then complete a survey when it ends.</p></div>';
    return;
  }

  timeline.innerHTML = visibleRehearsals.map((rehearsal) => {
    const date = new Date(rehearsal.start_time);
    const validDate = !Number.isNaN(date.getTime());
    const day = validDate ? String(date.getDate()).padStart(2, '0') : '--';
    const month = validDate ? date.toLocaleString('en', { month: 'short' }).toUpperCase() : 'DATE';
    const time = validDate ? date.toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' }) : 'Time not set';
    const endTime = validDate
      ? new Date(date.getTime() + Number(rehearsal.duration_minutes || 0) * 60000).toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' })
      : '';
    const completed = rehearsal.status === 'completed' || Number(rehearsal.review_completed) === 1;
    const finished = completed || rehearsal.status === 'cancelled' || (validDate && date.getTime() <= Date.now());
    const status = rehearsal.status === 'cancelled'
      ? 'Cancelled'
      : completed
        ? 'Completed'
        : finished
          ? 'Ready for review'
          : 'Upcoming';
    const songsPractised = String(rehearsal.song_titles || '').split(',').filter(Boolean).join(' · ');
    const detail = songsPractised || rehearsal.goals || 'No songs or goals added yet';
    const actions = [];
    if (rehearsal.status !== 'cancelled') {
      actions.push(`<button class="button button-outline" type="button" data-rehearsal-availability="${Number(rehearsal.id)}">Availability</button>`);
      if (rehearsal.status === 'planned' && currentBand?.user_role === 'owner') {
        actions.push(`<button class="button button-outline" type="button" data-rehearsal-edit="${Number(rehearsal.id)}">Edit</button>`);
      }
      if (finished) {
        actions.push(`<button class="button button-outline" type="button" data-rehearsal-review="${Number(rehearsal.id)}">${Number(rehearsal.review_completed) === 1 ? 'Edit survey' : 'Post-rehearsal survey'}</button>`);
      } else {
        actions.push('<button class="button button-outline" type="button" data-rehearsal-plan>Create AI plan</button>');
      }
    }
    return `
      <article class="timeline-card${finished ? '' : ' is-next'}">
        <div class="date-block${finished ? ' muted' : ''}"><strong>${safeText(day)}</strong><span>${safeText(month)}</span></div>
        <div>
          <span class="status-pill${finished ? ' quiet' : ''}">${safeText(status)}</span>
          <h3>${safeText(rehearsal.title)}</h3>
          <p>${safeText(time)}${endTime ? `–${safeText(endTime)}` : ''} · ${safeText(rehearsal.location || 'Location not set')}</p>
          <small>${safeText(detail)}</small>
        </div>
        <div class="timeline-card-actions">${actions.join('')}</div>
      </article>`;
  }).join('');

  timeline.querySelectorAll('[data-rehearsal-review]').forEach((button) => {
    button.addEventListener('click', () => openRehearsalReview(Number(button.dataset.rehearsalReview)));
  });
  timeline.querySelectorAll('[data-rehearsal-plan]').forEach((button) => {
    button.addEventListener('click', () => showView('assistant'));
  });
  timeline.querySelectorAll('[data-rehearsal-edit]').forEach((button) => {
    button.addEventListener('click', () => openRehearsalForm('edit', Number(button.dataset.rehearsalEdit)));
  });
  timeline.querySelectorAll('[data-rehearsal-availability]').forEach((button) => {
    button.addEventListener('click', () => openAvailability(Number(button.dataset.rehearsalAvailability)));
  });
}

async function loadRehearsals() {
  if (!currentBand || !apiOnline) {
    rehearsals = [];
    renderRehearsals();
    return;
  }
  try {
    const data = await request(`/bands/${currentBand.id}/rehearsals`);
    if (!Array.isArray(data.rehearsals)) throw new Error('The server returned invalid rehearsal data.');
    rehearsals = data.rehearsals;
  } catch (error) {
    rehearsals = [];
    showToast(error.message);
  }
  renderRehearsals();
}

async function loadMembers() {
  if (!currentBand || !apiOnline) {
    members = [];
    return;
  }
  const data = await request(`/bands/${currentBand.id}/members`);
  if (!Array.isArray(data.members)) throw new Error('The server returned invalid member data.');
  members = data.members;
  renderMemberManager(Boolean(data.can_edit));
}

function renderMemberManager(canEdit = currentBand?.user_role === 'owner') {
  const list = document.getElementById('memberManagerList');
  if (!members.length) {
    list.innerHTML = '<div class="empty-state"><strong>No members yet</strong><p>Add the first member to this band.</p></div>';
    return;
  }
  list.innerHTML = members.map((member) => `
    <button class="member-manager-row" type="button" data-member-edit="${Number(member.id)}"${canEdit ? '' : ' disabled'}>
      <span class="band-avatar">${safeText(initials(member.display_name))}</span>
      <span><strong>${safeText(member.display_name)}</strong><small>${safeText(member.band_role || 'Role not set')}</small></span>
      <span class="member-access">${Number(member.is_owner) === 1 ? 'Owner' : 'Member'}${canEdit ? ' · Edit' : ''}</span>
    </button>`).join('');
  list.querySelectorAll('[data-member-edit]').forEach((button) => {
    button.addEventListener('click', () => openMemberForm('edit', Number(button.dataset.memberEdit)));
  });
  document.getElementById('addMemberButton').classList.toggle('is-hidden', !canEdit);
}

async function openMemberManager() {
  if (!ensureCurrentBand()) return;
  document.getElementById('memberManagerMessage').textContent = '';
  try {
    await loadMembers();
    closeDialog('bandManagerModal');
    openDialog('memberManagerModal');
  } catch (error) {
    showToast(error.message);
  }
}

function openMemberForm(mode, memberId = null) {
  const form = document.getElementById('memberForm');
  const member = members.find((item) => Number(item.id) === Number(memberId));
  form.reset();
  form.elements.mode.value = mode;
  form.elements.member_id.value = memberId || '';
  document.getElementById('memberFormMessage').textContent = '';
  document.getElementById('memberFormTitle').textContent = mode === 'edit' ? 'Edit member' : 'Add member';
  document.getElementById('removeMemberButton').classList.toggle('is-hidden', mode !== 'edit' || Number(member?.is_owner) === 1);
  if (member) {
    form.elements.display_name.value = member.display_name;
    form.elements.band_role.value = member.band_role || '';
  }
  closeDialog('memberManagerModal');
  openDialog('memberFormModal');
}

async function saveMember(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const input = formDataObject(form);
  const message = document.getElementById('memberFormMessage');
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Save member';
  message.textContent = '';
  setSubmitting(form, true);
  try {
    const editing = input.mode === 'edit';
    const path = editing ? `/bands/${currentBand.id}/members/${Number(input.member_id)}` : `/bands/${currentBand.id}/members`;
    const data = await request(path, {
      method: editing ? 'PATCH' : 'POST',
      body: JSON.stringify({ display_name: input.display_name, band_role: input.band_role })
    });
    members = data.members || [];
    closeDialog('memberFormModal');
    renderMemberManager(Boolean(data.can_edit));
    openDialog('memberManagerModal');
    await loadBands();
    showToast(editing ? 'Member updated.' : 'Member added.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

async function removeMember() {
  const form = document.getElementById('memberForm');
  const memberId = Number(form.elements.member_id.value);
  const message = document.getElementById('memberFormMessage');
  const button = document.getElementById('removeMemberButton');
  button.disabled = true;
  try {
    const data = await request(`/bands/${currentBand.id}/members/${memberId}`, { method: 'DELETE' });
    members = data.members || [];
    closeDialog('memberFormModal');
    renderMemberManager(Boolean(data.can_edit));
    openDialog('memberManagerModal');
    await loadBands();
    showToast('Member removed.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    button.disabled = false;
  }
}

function reviewSelectOptions(options, selectedValue) {
  return options.map(([value, label]) => `<option value="${value}"${String(selectedValue) === value ? ' selected' : ''}>${safeText(label)}</option>`).join('');
}

function updateReviewSongVisibility() {
  const form = document.getElementById('rehearsalReviewForm');
  const selectedIds = [...form.querySelectorAll('[data-review-song-toggle]:checked')].map((input) => input.value);
  form.querySelectorAll('[data-review-song-card]').forEach((card) => {
    const selected = selectedIds.includes(card.dataset.reviewSongCard);
    card.classList.toggle('is-hidden', !selected);
    card.querySelectorAll('[data-review-field]').forEach((control) => {
      control.required = selected && control.dataset.reviewField !== 'note';
    });
  });
  document.getElementById('reviewSongCount').textContent = `${selectedIds.length} selected`;
}

function renderRehearsalReviewSongs(payload) {
  const picker = document.getElementById('reviewSongPicker');
  const forms = document.getElementById('reviewSongForms');
  const hasSavedReview = Boolean(payload.completed);
  const canEdit = Boolean(payload.can_edit);

  picker.innerHTML = payload.songs.map((song) => {
    const selected = hasSavedReview ? song.performance_rating !== null : Number(song.planned) === 1;
    return `<label class="review-song-choice">
      <input type="checkbox" value="${Number(song.id)}" data-review-song-toggle${selected ? ' checked' : ''}${canEdit ? '' : ' disabled'}>
      <span><strong>${safeText(song.title)}</strong><small>${safeText(song.artist || 'Unknown artist')}</small></span>
    </label>`;
  }).join('');

  forms.innerHTML = payload.songs.map((song) => {
    const rating = song.performance_rating ?? '';
    const progress = songProgressInfo(song.progress_level_after ?? song.progress_level ?? 'starting');
    const status = song.status_after ?? song.status ?? 'learning';
    const problem = song.problem_type ?? 'none';
    return `<article class="review-song-card" data-review-song-card="${Number(song.id)}">
      <div class="review-song-card-heading">
        <div><p class="eyebrow">SONG REVIEW</p><h4>${safeText(song.title)}</h4><small>${safeText(song.artist || 'Unknown artist')}</small></div>
        <span>${safeText(progress.label)}</span>
      </div>
      <div class="review-song-fields">
        <label>How did it go?
          <select data-review-field="performance_rating"${canEdit ? '' : ' disabled'}>
            <option value="">Choose a rating</option>
            ${reviewSelectOptions([['1', '1 · Needs a reset'], ['2', '2 · Needs work'], ['3', '3 · Improving'], ['4', '4 · Good'], ['5', '5 · Ready']], rating)}
          </select>
        </label>
        <label>Progress after rehearsal
          <select data-review-field="progress_level_after"${canEdit ? '' : ' disabled'}>
            ${reviewSelectOptions(songProgressLevels, progress.value)}
          </select>
        </label>
        <label>Status after rehearsal
          <select data-review-field="status_after"${canEdit ? '' : ' disabled'}>
            ${reviewSelectOptions([['learning', 'Learning'], ['practising', 'Practising'], ['ready', 'Ready']], status)}
          </select>
        </label>
        <label>Main problem
          <select data-review-field="problem_type"${canEdit ? '' : ' disabled'}>
            ${reviewSelectOptions([['none', 'No main problem'], ['rhythm', 'Rhythm'], ['coordination', 'Coordination'], ['technique', 'Technique'], ['tone', 'Tone'], ['memory', 'Memory'], ['other', 'Other']], problem)}
          </select>
        </label>
      </div>
      <label>Song notes
        <textarea rows="2" maxlength="500" data-review-field="note" placeholder="What should the band remember next time?"${canEdit ? '' : ' disabled'}>${safeText(song.review_note || '')}</textarea>
      </label>
    </article>`;
  }).join('');

  picker.querySelectorAll('[data-review-song-toggle]').forEach((checkbox) => {
    checkbox.addEventListener('change', updateReviewSongVisibility);
  });
  updateReviewSongVisibility();
}

async function openRehearsalReview(rehearsalId) {
  const message = document.getElementById('rehearsalReviewMessage');
  message.textContent = '';
  try {
    const payload = await request(`/rehearsals/${rehearsalId}/review`);
    activeRehearsalReview = payload;
    const form = document.getElementById('rehearsalReviewForm');
    form.reset();
    document.getElementById('rehearsalReviewTitle').textContent = payload.rehearsal.title;
    const date = new Date(payload.rehearsal.start_time);
    const dateLabel = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('en', { dateStyle: 'medium', timeStyle: 'short' });
    document.getElementById('rehearsalReviewIntro').textContent = `${dateLabel}${payload.rehearsal.location ? ` · ${payload.rehearsal.location}` : ''}. Record the overall result, then review each song separately.`;
    form.elements.overall_rating.value = payload.review?.overall_rating ?? '';
    form.elements.goals_met.value = payload.review?.goals_met ?? '';
    form.elements.notes.value = payload.review?.notes ?? '';
    [form.elements.overall_rating, form.elements.goals_met, form.elements.notes].forEach((control) => { control.disabled = !payload.can_edit; });
    const submit = form.querySelector('button[type="submit"]');
    submit.classList.toggle('is-hidden', !payload.can_edit);
    submit.dataset.label = payload.completed ? 'Update rehearsal survey' : 'Save rehearsal survey';
    submit.textContent = submit.dataset.label;
    renderRehearsalReviewSongs(payload);
    form.scrollTop = 0;
    openDialog('rehearsalReviewModal');
  } catch (error) {
    showToast(error.message);
  }
}

async function saveRehearsalReview(event) {
  event.preventDefault();
  if (!activeRehearsalReview?.can_edit) return;
  const form = event.currentTarget;
  const message = document.getElementById('rehearsalReviewMessage');
  const selected = [...form.querySelectorAll('[data-review-song-toggle]:checked')];
  if (!selected.length) {
    message.textContent = 'Choose at least one song from this rehearsal.';
    return;
  }
  const songAnswers = selected.map((checkbox) => {
    const card = form.querySelector(`[data-review-song-card="${checkbox.value}"]`);
    const value = (field) => card.querySelector(`[data-review-field="${field}"]`).value;
    return {
      song_id: Number(checkbox.value),
      performance_rating: Number(value('performance_rating')),
      progress_level_after: value('progress_level_after'),
      status_after: value('status_after'),
      problem_type: value('problem_type'),
      note: value('note').trim()
    };
  });

  message.textContent = '';
  setSubmitting(form, true);
  try {
    await request(`/rehearsals/${activeRehearsalReview.rehearsal.id}/review`, {
      method: 'PUT',
      body: JSON.stringify({
        overall_rating: Number(form.elements.overall_rating.value),
        goals_met: form.elements.goals_met.value,
        notes: form.elements.notes.value.trim(),
        songs: songAnswers
      })
    });
    closeDialog('rehearsalReviewModal');
    await Promise.all([loadSongs(), loadRehearsals()]);
    showToast('Rehearsal survey saved. Song progress is now up to date.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

async function openAvailability(rehearsalId) {
  const message = document.getElementById('availabilityFormMessage');
  message.textContent = '';
  try {
    activeAvailability = await request(`/rehearsals/${rehearsalId}/availability`);
    document.getElementById('availabilityTitle').textContent = activeAvailability.rehearsal.title;
    const date = new Date(activeAvailability.rehearsal.start_time);
    document.getElementById('availabilityIntro').textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleString('en', { dateStyle: 'medium', timeStyle: 'short' });
    document.getElementById('availabilityFormList').innerHTML = activeAvailability.members.map((member) => `
      <article class="availability-form-row" data-availability-member="${Number(member.member_id)}">
        <div><strong>${safeText(member.display_name)}</strong><small>${safeText(member.band_role || 'Role not set')}</small></div>
        <select data-availability-field="status"${member.can_edit ? '' : ' disabled'}>
          ${reviewSelectOptions([['available', 'Available'], ['unsure', 'Not sure'], ['unavailable', 'Unavailable']], member.status)}
        </select>
        <input data-availability-field="note" maxlength="300" value="${safeText(member.note || '')}" placeholder="Optional note"${member.can_edit ? '' : ' disabled'}>
      </article>`).join('');
    document.querySelector('#availabilityForm button[type="submit"]').classList.toggle('is-hidden', !activeAvailability.members.some((member) => member.can_edit));
    openDialog('availabilityModal');
  } catch (error) {
    showToast(error.message);
  }
}

async function saveAvailability(event) {
  event.preventDefault();
  if (!activeAvailability) return;
  const form = event.currentTarget;
  const message = document.getElementById('availabilityFormMessage');
  const editableMembers = activeAvailability.members.filter((member) => member.can_edit);
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Save availability';
  message.textContent = '';
  setSubmitting(form, true);
  try {
    await Promise.all(editableMembers.map((member) => {
      const row = form.querySelector(`[data-availability-member="${Number(member.member_id)}"]`);
      return request(`/rehearsals/${activeAvailability.rehearsal.id}/availability/${Number(member.member_id)}`, {
        method: 'PUT',
        body: JSON.stringify({
          status: row.querySelector('[data-availability-field="status"]').value,
          note: row.querySelector('[data-availability-field="note"]').value.trim()
        })
      });
    }));
    closeDialog('availabilityModal');
    showToast('Member availability saved.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

function renderReviewHistory() {
  const songFilter = document.getElementById('historySongFilter').value;
  const problemFilter = document.getElementById('historyProblemFilter').value;
  const filtered = reviewHistory.filter((item) =>
    (songFilter === 'all' || String(item.song_id) === songFilter)
    && (problemFilter === 'all' || item.problem_type === problemFilter));
  const list = document.getElementById('reviewHistoryList');
  if (!filtered.length) {
    list.innerHTML = '<div class="empty-state"><strong>No matching survey history</strong><p>Complete a post-rehearsal survey or change the filters.</p></div>';
    return;
  }
  const groups = new Map();
  filtered.forEach((item) => {
    if (!groups.has(item.rehearsal_id)) groups.set(item.rehearsal_id, { ...item, songs: [] });
    if (item.song_id) groups.get(item.rehearsal_id).songs.push(item);
  });
  list.innerHTML = [...groups.values()].map((review) => {
    const date = new Date(review.start_time);
    const dateLabel = Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString('en', { dateStyle: 'medium' });
    return `<article class="history-card">
      <div class="history-card-heading"><div><p class="eyebrow">${safeText(dateLabel)}</p><h3>${safeText(review.rehearsal_title)}</h3></div><span>${Number(review.overall_rating)}/5</span></div>
      <p>${safeText(review.overall_notes || 'No overall notes')}</p>
      <div class="history-song-list">${review.songs.map((song) => `<div><strong>${safeText(song.song_title)}</strong><span>${safeText(songProgressInfo(song.progress_level_after).label)} · ${safeText(song.problem_type || 'none')}</span><small>${safeText(song.note || 'No song note')}</small></div>`).join('')}</div>
    </article>`;
  }).join('');
}

async function openReviewHistory() {
  if (!ensureCurrentBand()) return;
  try {
    const data = await request(`/bands/${currentBand.id}/review-history`);
    reviewHistory = Array.isArray(data.history) ? data.history : [];
    const songsWithHistory = [...new Map(reviewHistory.filter((item) => item.song_id).map((item) => [String(item.song_id), item.song_title])).entries()];
    document.getElementById('historySongFilter').innerHTML = '<option value="all">All songs</option>'
      + songsWithHistory.map(([id, title]) => `<option value="${Number(id)}">${safeText(title)}</option>`).join('');
    document.getElementById('historyProblemFilter').value = 'all';
    renderReviewHistory();
    openDialog('reviewHistoryModal');
  } catch (error) {
    showToast(error.message);
  }
}

async function loadBands() {
  if (!apiOnline) {
    bands = [];
    currentBand = null;
    updateBandUI();
    return;
  }

  const data = await request('/bands');
  if (!Array.isArray(data.bands)) throw new Error('The server returned invalid band data.');
  bands = data.bands;
  if (bands.length === 0) {
    currentBand = null;
    updateBandUI();
    return;
  }
  let savedBandId = null;
  try { savedBandId = Number(localStorage.getItem(userStorageKey('current-band', false))); } catch {}
  currentBand = bands.find((band) => Number(band.id) === savedBandId) || bands[0];
  updateBandUI();
}

async function loadCurrentUser() {
  if (!apiOnline) {
    updateUserUI();
    return;
  }
  const path = currentBand ? `/users/me?band_id=${currentBand.id}` : '/users/me';
  const data = await request(path);
  if (!data.user) throw new Error('The server did not return the current user.');
  currentUser = data.user;
  updateUserUI();
}

function updateQuestionnaireCard() {
  const percent = Math.max(0, Math.min(100, Number(currentQuestionnaire?.completion_percent) || 0));
  document.getElementById('setupProgressText').textContent = percent === 100 ? 'Complete' : percent === 0 ? 'Not started' : `${percent}% complete`;
  document.getElementById('setupProgressBar').style.width = `${percent}%`;
  document.getElementById('finishSetupButton').textContent = percent === 100 ? 'Edit questionnaire →' : 'Complete questionnaire →';
}

async function loadQuestionnaire() {
  if (!currentBand || !apiOnline) {
    currentQuestionnaire = { answers: null, completion_percent: 0, completed: false };
    updateQuestionnaireCard();
    return;
  }
  const data = await request(`/bands/${currentBand.id}/questionnaire`);
  currentQuestionnaire = data;
  updateQuestionnaireCard();
}

function showQuestionnaireStep(step) {
  questionnaireStep = Math.max(1, Math.min(3, step));
  const labels = ['Your sound', 'Your routine', 'Your challenge'];
  document.querySelectorAll('[data-questionnaire-step]').forEach((panel) => {
    const active = Number(panel.dataset.questionnaireStep) === questionnaireStep;
    panel.hidden = !active;
    panel.classList.toggle('is-active', active);
  });
  document.getElementById('questionnaireStepLabel').textContent = `Step ${questionnaireStep} of 3 · ${labels[questionnaireStep - 1]}`;
  document.getElementById('questionnaireProgressBar').style.width = `${questionnaireStep * 33.333}%`;
  document.getElementById('questionnaireBackButton').classList.toggle('is-hidden', questionnaireStep === 1);
  document.getElementById('questionnaireNextButton').classList.toggle('is-hidden', questionnaireStep === 3);
  document.getElementById('saveQuestionnaireButton').classList.toggle('is-hidden', questionnaireStep !== 3);
}

function validateQuestionnaireStep() {
  const panel = document.querySelector(`[data-questionnaire-step="${questionnaireStep}"]`);
  const controls = [...panel.querySelectorAll('input, select, textarea')].filter((control) => !control.closest('.is-hidden'));
  for (const control of controls) {
    if (!control.checkValidity()) {
      control.reportValidity();
      return false;
    }
  }
  return true;
}

function openQuestionnaire() {
  if (currentBand && currentBand.user_role !== 'owner') {
    showToast('Only the band owner can edit this questionnaire.');
    return;
  }
  const form = document.getElementById('questionnaireForm');
  form.reset();
  const needsBand = !currentBand;
  const bandFields = document.getElementById('questionnaireBandFields');
  bandFields.classList.toggle('is-hidden', !needsBand);
  form.elements.band_name.required = needsBand;
  document.querySelectorAll('.questionnaire-close').forEach((button) => button.classList.toggle('is-hidden', needsBand));
  document.getElementById('questionnaireTitle').textContent = needsBand ? 'Set up your first band' : 'Tell us about your band';
  document.getElementById('questionnaireFormMessage').textContent = '';

  const answers = currentQuestionnaire?.answers || {};
  const values = {
    instrument: answers.instrument || currentUser?.instrument || '',
    genres: answers.genres || '',
    experience_level: answers.experience_level || '',
    main_goal: answers.main_goal || '',
    rehearsal_frequency: answers.rehearsal_frequency || '',
    session_minutes: answers.session_minutes || '',
    main_challenge: answers.main_challenge || '',
    notes: answers.notes || ''
  };
  Object.entries(values).forEach(([name, value]) => {
    if (form.elements[name]) form.elements[name].value = value;
  });
  showQuestionnaireStep(1);
  openDialog('questionnaireModal');
}

async function saveQuestionnaire(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const message = document.getElementById('questionnaireFormMessage');
  const submit = document.getElementById('saveQuestionnaireButton');
  submit.dataset.label = 'Save questionnaire';
  message.textContent = '';
  setSubmitting(form, true);
  try {
    const input = formDataObject(form);
    let createdFirstBand = false;
    if (!currentBand) {
      const bandData = await request('/bands', {
        method: 'POST',
        body: JSON.stringify({ name: input.band_name, description: input.band_description })
      });
      if (!bandData.band) throw new Error('The server did not return the new band.');
      currentBand = bandData.band;
      bands = [currentBand];
      createdFirstBand = true;
      try { localStorage.setItem(userStorageKey('current-band', false), String(currentBand.id)); } catch {}
      updateBandUI();
    }

    const data = await request(`/bands/${currentBand.id}/questionnaire`, {
      method: 'PUT',
      body: JSON.stringify({
        instrument: input.instrument,
        genres: input.genres,
        experience_level: input.experience_level,
        main_goal: input.main_goal,
        rehearsal_frequency: input.rehearsal_frequency,
        session_minutes: Number(input.session_minutes),
        main_challenge: input.main_challenge,
        notes: input.notes
      })
    });
    currentQuestionnaire = data;
    updateQuestionnaireCard();
    await loadCurrentUser();
    if (createdFirstBand) {
      await loadSongs();
      await loadRehearsals();
      restoreLocalChoices();
    }
    closeDialog('questionnaireModal');
    showView('songs');
    showToast(createdFirstBand ? 'Your first band is ready.' : 'Questionnaire saved.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

function renderBandList() {
  const list = document.getElementById('bandList');
  if (bands.length === 0) {
    list.innerHTML = '<div class="empty-state"><strong>No bands yet</strong><p>Create your first band to begin.</p></div>';
    return;
  }
  list.innerHTML = bands.map((band) => {
    const selected = Number(band.id) === Number(currentBand.id);
    const description = band.description || `${band.member_count || 1} member${Number(band.member_count) === 1 ? '' : 's'}`;
    return `<button class="band-list-button${selected ? ' is-selected' : ''}" type="button" data-band-id="${Number(band.id)}">
      <span class="band-avatar">${safeText(initials(band.name))}</span>
      <span><strong>${safeText(band.name)}</strong><small>${safeText(description)}</small></span>
      <span class="band-check">${selected ? '✓' : '→'}</span>
    </button>`;
  }).join('');

  list.querySelectorAll('[data-band-id]').forEach((button) => {
    button.addEventListener('click', () => selectBand(Number(button.dataset.bandId)));
  });
}

async function selectBand(bandId) {
  const nextBand = bands.find((band) => Number(band.id) === Number(bandId));
  if (!nextBand) return;
  currentBand = nextBand;
  try { localStorage.setItem(userStorageKey('current-band', false), String(currentBand.id)); } catch {}
  updateBandUI();
  renderBandList();
  currentSongFilter = 'all';
  document.querySelectorAll('[data-song-filter]').forEach((item) => item.classList.toggle('is-active', item.dataset.songFilter === 'all'));
  try {
    await loadCurrentUser();
    await loadSongs();
    await loadRehearsals();
    restoreLocalChoices();
    await loadQuestionnaire();
    closeDialog('bandManagerModal');
    showView('songs');
    showToast(`${currentBand.name} selected.`);
  } catch (error) {
    document.getElementById('bandManagerMessage').textContent = error.message;
  }
}

function openBandManager() {
  document.getElementById('bandManagerMessage').textContent = '';
  renderBandList();
  document.getElementById('editBandButton').disabled = !currentBand || currentBand.user_role !== 'owner';
  openDialog('bandManagerModal');
}

function openBandForm(mode) {
  if (mode === 'edit' && !currentBand) return;
  const form = document.getElementById('bandForm');
  form.reset();
  form.elements.mode.value = mode;
  document.getElementById('bandFormMessage').textContent = '';
  document.getElementById('bandFormTitle').textContent = mode === 'edit' ? 'Edit band' : 'Create a band';
  if (mode === 'edit') {
    form.elements.name.value = currentBand.name;
    form.elements.description.value = currentBand.description || '';
  }
  closeDialog('bandManagerModal');
  openDialog('bandFormModal');
}

async function saveBand(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const input = formDataObject(form);
  const message = document.getElementById('bandFormMessage');
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Save band';
  message.textContent = '';
  setSubmitting(form, true);
  try {
    if (!apiOnline) throw new Error('Band changes require the PHP server.');
    const mode = input.mode;
    const path = mode === 'edit' ? `/bands/${currentBand.id}` : '/bands';
    const data = await request(path, {
      method: mode === 'edit' ? 'PATCH' : 'POST',
      body: JSON.stringify({ name: input.name, description: input.description })
    });
    if (!data.band) throw new Error('The server did not return the saved band.');
    if (mode === 'edit') {
      bands = bands.map((band) => Number(band.id) === Number(data.band.id) ? data.band : band);
    } else {
      bands.push(data.band);
    }
    currentBand = data.band;
    try { localStorage.setItem(userStorageKey('current-band', false), String(currentBand.id)); } catch {}
    updateBandUI();
    await loadCurrentUser();
    await loadSongs();
    await loadRehearsals();
    restoreLocalChoices();
    await loadQuestionnaire();
    closeDialog('bandFormModal');
    showView('songs');
    if (mode === 'edit') {
      showToast('Band updated.');
    } else {
      showToast('Band created. Complete its questionnaire next.');
      openQuestionnaire();
    }
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

function openProfileForm() {
  if (!currentBand) {
    openQuestionnaire();
    return;
  }
  const form = document.getElementById('profileForm');
  form.elements.name.value = currentUser.name || '';
  form.elements.email.value = currentUser.email || '';
  form.elements.instrument.value = currentUser.instrument || '';
  document.getElementById('profileFormMessage').textContent = '';
  openDialog('profileModal');
}

async function saveProfile(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const input = formDataObject(form);
  const message = document.getElementById('profileFormMessage');
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Save profile';
  message.textContent = '';
  setSubmitting(form, true);
  try {
    if (!apiOnline) throw new Error('Profile changes require the PHP server.');
    const data = await request(`/users/me?band_id=${currentBand.id}`, {
      method: 'PATCH',
      body: JSON.stringify({ name: input.name, email: input.email, instrument: input.instrument })
    });
    if (!data.user) throw new Error('The server did not return the saved profile.');
    currentUser = data.user;
    updateUserUI();
    closeDialog('profileModal');
    showToast('Profile updated.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

function showToast(message) {
  const toast = document.getElementById('toast');
  toast.textContent = message;
  toast.classList.add('is-visible');
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.remove('is-visible'), 2800);
}

function openDialog(id) {
  const dialog = document.getElementById(id);
  if (dialog && !dialog.open) dialog.showModal();
}

function closeDialog(id) {
  const dialog = document.getElementById(id);
  if (dialog?.open) dialog.close();
}

function showInfo(eyebrow, title, body) {
  document.getElementById('infoEyebrow').textContent = eyebrow;
  document.getElementById('infoTitle').textContent = title;
  document.getElementById('infoBody').innerHTML = body;
  openDialog('infoModal');
}

function setSubmitting(form, isSubmitting) {
  const button = form.querySelector('button[type="submit"]');
  button.disabled = isSubmitting;
  button.textContent = isSubmitting ? 'Saving…' : button.dataset.label || button.textContent.replace('Saving…', 'Save');
}

function formDataObject(form) {
  return Object.fromEntries(new FormData(form).entries());
}

function openSongForm(mode, songId = null) {
  if (!ensureCurrentBand()) return;
  const form = document.getElementById('songForm');
  const song = songs.find((item) => Number(item.id) === Number(songId));
  form.reset();
  form.elements.mode.value = mode;
  form.elements.song_id.value = songId || '';
  document.getElementById('songFormTitle').textContent = mode === 'edit' ? 'Edit song' : 'Add a song';
  document.getElementById('songFormMessage').textContent = '';
  document.getElementById('archiveSongButton').classList.toggle('is-hidden', mode !== 'edit');
  if (song) {
    form.elements.title.value = song.title;
    form.elements.artist.value = song.artist || '';
    form.elements.progress_level.value = song.progress_level;
    form.elements.status.value = song.status;
    form.elements.problem_notes.value = song.problem_notes || '';
  }
  openDialog('songModal');
}

async function saveSong(event) {
  event.preventDefault();
  if (!ensureCurrentBand()) return;
  const form = event.currentTarget;
  const message = document.getElementById('songFormMessage');
  const input = formDataObject(form);
  const song = {
    title: String(input.title || '').trim(), artist: String(input.artist || '').trim(),
    progress_level: String(input.progress_level || 'starting'), status: String(input.status || 'learning'),
    problem_notes: String(input.problem_notes || '').trim()
  };
  if (!song.title) {
    message.textContent = 'Please enter a song title.';
    return;
  }

  message.textContent = '';
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Save song';
  setSubmitting(form, true);
  try {
    if (apiOnline) {
      const editing = input.mode === 'edit';
      const path = editing ? `/bands/${currentBand.id}/songs/${Number(input.song_id)}` : `/bands/${currentBand.id}/songs`;
      const data = await request(path, { method: editing ? 'PATCH' : 'POST', body: JSON.stringify(song) });
      if (!data.song) throw new Error('The server did not return the saved song.');
      songs = editing
        ? songs.map((item) => Number(item.id) === Number(data.song.id) ? data.song : item)
        : [data.song, ...songs];
      showToast(editing ? 'Song updated.' : 'Song saved to BandPilot.');
    } else {
      songs.unshift({ ...song, id: Date.now() });
      showToast('Song added for this preview only.');
    }
    renderSongs();
    form.reset();
    closeDialog('songModal');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

async function archiveSong() {
  const form = document.getElementById('songForm');
  const songId = Number(form.elements.song_id.value);
  const message = document.getElementById('songFormMessage');
  const button = document.getElementById('archiveSongButton');
  button.disabled = true;
  try {
    await request(`/bands/${currentBand.id}/songs/${songId}`, { method: 'DELETE' });
    songs = songs.filter((song) => Number(song.id) !== songId);
    renderSongs();
    closeDialog('songModal');
    showToast('Song archived. Its rehearsal history was kept.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    button.disabled = false;
  }
}

function renderRehearsalSongPicker(selectedIds = []) {
  const selected = selectedIds.map(Number);
  const picker = document.getElementById('rehearsalSongPicker');
  picker.innerHTML = songs.length ? songs.map((song) => `<label><input type="checkbox" value="${Number(song.id)}" data-rehearsal-song${selected.includes(Number(song.id)) ? ' checked' : ''}><span><strong>${safeText(song.title)}</strong><small>${safeText(songProgressInfo(song.progress_level).label)}</small></span></label>`).join('') : '<p>Add a song before planning a rehearsal.</p>';
}

function openRehearsalForm(mode, rehearsalId = null) {
  if (!ensureCurrentBand()) return;
  const form = document.getElementById('rehearsalForm');
  const rehearsal = rehearsals.find((item) => Number(item.id) === Number(rehearsalId));
  form.reset();
  form.elements.mode.value = mode;
  form.elements.rehearsal_id.value = rehearsalId || '';
  document.getElementById('rehearsalFormTitle').textContent = mode === 'edit' ? 'Edit rehearsal' : 'Plan a rehearsal';
  document.getElementById('rehearsalFormMessage').textContent = '';
  document.getElementById('cancelRehearsalButton').classList.toggle('is-hidden', mode !== 'edit');
  if (rehearsal) {
    form.elements.title.value = rehearsal.title;
    form.elements.start_time.value = String(rehearsal.start_time).slice(0, 16);
    form.elements.duration_minutes.value = rehearsal.duration_minutes;
    form.elements.location.value = rehearsal.location || '';
    form.elements.goals.value = rehearsal.goals || '';
  }
  const selectedIds = rehearsal?.song_ids ? String(rehearsal.song_ids).split(',').map(Number) : [];
  renderRehearsalSongPicker(selectedIds);
  openDialog('rehearsalModal');
}

async function saveRehearsal(event) {
  event.preventDefault();
  if (!ensureCurrentBand()) return;
  const form = event.currentTarget;
  const message = document.getElementById('rehearsalFormMessage');
  const input = formDataObject(form);
  const songIds = [...form.querySelectorAll('[data-rehearsal-song]:checked')].map((checkbox) => Number(checkbox.value));
  const rehearsal = {
    title: input.title, start_time: input.start_time,
    duration_minutes: Number(input.duration_minutes || 0),
    location: input.location, goals: input.goals, song_ids: songIds
  };
  if (!songIds.length) {
    message.textContent = 'Choose at least one song for this rehearsal.';
    return;
  }
  message.textContent = '';
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Save rehearsal';
  setSubmitting(form, true);
  try {
    let saved = { ...rehearsal, id: Date.now() };
    if (apiOnline) {
      const editing = input.mode === 'edit';
      const path = editing ? `/bands/${currentBand.id}/rehearsals/${Number(input.rehearsal_id)}` : `/bands/${currentBand.id}/rehearsals`;
      const data = await request(path, { method: editing ? 'PATCH' : 'POST', body: JSON.stringify(rehearsal) });
      if (!data.rehearsal) throw new Error('The server did not return the rehearsal.');
      saved = data.rehearsal;
    }
    if (apiOnline) {
      await loadRehearsals();
    } else {
      rehearsals.unshift(saved);
      renderRehearsals();
    }
    closeDialog('rehearsalModal');
    form.reset();
    showView('rehearsals');
    showToast(apiOnline ? (input.mode === 'edit' ? 'Rehearsal updated.' : 'Rehearsal saved.') : 'Rehearsal added for this preview only.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

async function cancelRehearsal() {
  const form = document.getElementById('rehearsalForm');
  const rehearsalId = Number(form.elements.rehearsal_id.value);
  const message = document.getElementById('rehearsalFormMessage');
  const button = document.getElementById('cancelRehearsalButton');
  button.disabled = true;
  try {
    await request(`/bands/${currentBand.id}/rehearsals/${rehearsalId}`, { method: 'DELETE' });
    closeDialog('rehearsalModal');
    await loadRehearsals();
    showToast('Rehearsal cancelled.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    button.disabled = false;
  }
}

async function savePerformance(event) {
  event.preventDefault();
  if (!ensureCurrentBand()) return;
  const form = event.currentTarget;
  const message = document.getElementById('performanceFormMessage');
  const input = formDataObject(form);
  const performance = { ...input, length_minutes: Number(input.length_minutes || 0) };
  message.textContent = '';
  const submit = form.querySelector('button[type="submit"]');
  submit.dataset.label = 'Save performance';
  setSubmitting(form, true);
  try {
    let saved = { ...performance, id: Date.now() };
    if (apiOnline) {
      const data = await request(`/bands/${currentBand.id}/performances`, { method: 'POST', body: JSON.stringify(performance) });
      if (!data.performance) throw new Error('The server did not return the performance.');
      saved = data.performance;
    }
    document.querySelector('#performancesView .section-heading h2').textContent = saved.name;
    document.querySelector('#performancesView .section-heading > div > p:last-child').textContent = `${saved.location || 'Location not set'} · ${saved.length_minutes} minute set`;
    document.querySelector('.performance-main h3').textContent = `${saved.length_minutes}-minute set · Setlist in progress`;
    closeDialog('performanceModal');
    form.reset();
    showView('performances');
    showToast(apiOnline ? 'Performance saved.' : 'Performance added for this preview only.');
  } catch (error) {
    message.textContent = error.message;
  } finally {
    setSubmitting(form, false);
  }
}

function renderSetlist(setlist) {
  document.querySelector('.setlist').innerHTML = setlist.map((name, index) => `
    <span><b>${String(index + 1).padStart(2, '0')}</b> ${safeText(name)} <small>--:--</small></span>`).join('');
}

function saveSetlist(event) {
  event.preventDefault();
  const input = formDataObject(event.currentTarget);
  const setlist = [input.song_1, input.song_2, input.song_3].map((name) => String(name || '').trim()).filter(Boolean);
  renderSetlist(setlist);
  try { localStorage.setItem(userStorageKey('setlist'), JSON.stringify(setlist)); } catch {}
  closeDialog('setlistModal');
  showToast('Setlist updated.');
}

function renderAiResult(kind) {
  const result = aiResults[kind] || aiResults.rehearsal;
  document.querySelectorAll('#assistantResult [contenteditable="true"]').forEach((element) => element.removeAttribute('contenteditable'));
  document.getElementById('assistantResult').classList.remove('is-editing');
  document.getElementById('resultType').textContent = result.type;
  document.querySelector('#assistantResult h3').textContent = result.title;
  document.querySelector('#assistantResult ol').innerHTML = result.items.map((item) => `
    <li><span>${safeText(item[0])}</span><div><strong>${safeText(item[1])}</strong><p>${safeText(item[2])}</p></div></li>`).join('');
  document.getElementById('resultStatus').textContent = 'Review before saving';
  document.getElementById('resultStatus').classList.remove('is-approved');
  const approveButton = document.getElementById('approvePlanButton');
  approveButton.disabled = false;
  approveButton.textContent = 'Approve and save';
  editingPlan = false;
  document.getElementById('editPlanButton').textContent = 'Edit plan';
  showView('assistant');
  showToast('AI result updated.');
}

function togglePlanEditing() {
  editingPlan = !editingPlan;
  const editable = document.querySelectorAll('#assistantResult h3, #assistantResult li p');
  editable.forEach((element) => element.setAttribute('contenteditable', String(editingPlan)));
  document.getElementById('assistantResult').classList.toggle('is-editing', editingPlan);
  document.getElementById('editPlanButton').textContent = editingPlan ? 'Finish editing' : 'Edit plan';
  showToast(editingPlan ? 'You can now edit the plan text.' : 'Plan edits kept.');
}

async function approvePlan() {
  if (!ensureCurrentBand()) return;
  if (editingPlan) togglePlanEditing();
  const button = document.getElementById('approvePlanButton');
  const items = Array.from(document.querySelectorAll('#assistantResult li')).map((item) => ({
    label: item.querySelector('span')?.textContent || '',
    title: item.querySelector('strong')?.textContent || '',
    note: item.querySelector('p')?.textContent || ''
  }));
  const content = { title: document.querySelector('#assistantResult h3').textContent, items };
  button.disabled = true;
  button.textContent = 'Saving…';
  try {
    if (apiOnline) {
      await request(`/bands/${currentBand.id}/ai-results`, {
        method: 'POST',
        body: JSON.stringify({ result_type: document.getElementById('resultType').textContent, content })
      });
    } else {
      localStorage.setItem(userStorageKey('approved-plan'), JSON.stringify(content));
    }
    document.getElementById('resultStatus').textContent = 'Approved and saved';
    document.getElementById('resultStatus').classList.add('is-approved');
    button.textContent = 'Saved';
    showToast('Plan approved and saved.');
  } catch (error) {
    button.disabled = false;
    button.textContent = 'Approve and save';
    showToast(error.message);
  }
}

function restoreLocalChoices() {
  const defaultChecks = {
    'guitar-amplifiers': true,
    'drum-hardware': true,
    'instrument-cables': true,
    'spare-strings': false,
    'power-extensions': false
  };
  document.querySelectorAll('[data-checklist-key]').forEach((checkbox) => {
    try {
      const stored = localStorage.getItem(`${userStorageKey('check')}-${checkbox.dataset.checklistKey}`);
      checkbox.checked = stored !== null ? stored === 'true' : defaultChecks[checkbox.dataset.checklistKey];
    } catch {}
  });
  updateChecklistCount();

  try {
    const storedSetlist = JSON.parse(localStorage.getItem(userStorageKey('setlist')) || 'null');
    const defaultSetlist = songs.slice(0, 3).map((song) => song.title);
    const setlist = Array.isArray(storedSetlist) && storedSetlist.length ? storedSetlist : defaultSetlist;
    document.querySelectorAll('#setlistForm input').forEach((input, index) => { input.value = setlist[index] || ''; });
    renderSetlist(setlist);
  } catch {}
}

function updateChecklistCount() {
  const boxes = [...document.querySelectorAll('[data-checklist-key]')];
  const done = boxes.filter((box) => box.checked).length;
  document.querySelector('.checklist-panel .panel-heading span').textContent = `${done} / ${boxes.length}`;
}

document.querySelectorAll('[data-view], [data-view-link]').forEach((button) => {
  button.addEventListener('click', () => showView(button.dataset.view || button.dataset.viewLink));
});

document.querySelectorAll('[data-auth-mode]').forEach((button) => {
  button.addEventListener('click', () => setAuthMode(button.dataset.authMode));
});

document.getElementById('loginForm').addEventListener('submit', login);
document.getElementById('registerForm').addEventListener('submit', register);
document.getElementById('useDemoButton').addEventListener('click', () => {
  const form = document.getElementById('loginForm');
  form.elements.email.value = 'demo@bandpilot.local';
  form.elements.password.value = 'BandPilot123!';
  form.elements.email.focus();
});

document.querySelectorAll('.brand').forEach((brand) => {
  brand.addEventListener('click', (event) => {
    event.preventDefault();
    if (currentUser) showView('overview');
  });
});

window.addEventListener('hashchange', () => {
  const view = window.location.hash.slice(1);
  if (pageTitles[view]) showView(view, false);
});

document.querySelectorAll('[data-action="new-rehearsal"]').forEach((button) => {
  button.addEventListener('click', () => openRehearsalForm('create'));
});

document.getElementById('menuButton').addEventListener('click', (event) => {
  const sidebar = document.getElementById('sidebar');
  const isOpen = sidebar.classList.toggle('is-open');
  event.currentTarget.setAttribute('aria-expanded', String(isOpen));
});

document.getElementById('addSongButton').addEventListener('click', () => openSongForm('create'));
document.getElementById('closeSongModal').addEventListener('click', () => closeDialog('songModal'));
document.getElementById('cancelSongModal').addEventListener('click', () => closeDialog('songModal'));
document.getElementById('archiveSongButton').addEventListener('click', archiveSong);
document.getElementById('cancelRehearsalButton').addEventListener('click', cancelRehearsal);
document.getElementById('songForm').addEventListener('submit', saveSong);
document.getElementById('rehearsalForm').addEventListener('submit', saveRehearsal);
document.getElementById('rehearsalReviewForm').addEventListener('submit', saveRehearsalReview);
document.getElementById('availabilityForm').addEventListener('submit', saveAvailability);
document.getElementById('memberForm').addEventListener('submit', saveMember);
document.getElementById('performanceForm').addEventListener('submit', savePerformance);
document.getElementById('setlistForm').addEventListener('submit', saveSetlist);
document.getElementById('bandForm').addEventListener('submit', saveBand);
document.getElementById('profileForm').addEventListener('submit', saveProfile);
document.getElementById('questionnaireForm').addEventListener('submit', saveQuestionnaire);
document.getElementById('logoutButton').addEventListener('click', logout);
document.getElementById('questionnaireBackButton').addEventListener('click', () => showQuestionnaireStep(questionnaireStep - 1));
document.getElementById('questionnaireNextButton').addEventListener('click', () => {
  if (validateQuestionnaireStep()) showQuestionnaireStep(questionnaireStep + 1);
});

document.querySelectorAll('[data-close-dialog]').forEach((button) => {
  button.addEventListener('click', () => closeDialog(button.dataset.closeDialog));
});

document.querySelectorAll('dialog').forEach((dialog) => {
  dialog.addEventListener('click', (event) => {
    if (event.target === dialog && !(dialog.id === 'questionnaireModal' && !currentBand)) dialog.close();
  });
  dialog.addEventListener('cancel', (event) => {
    if (dialog.id === 'questionnaireModal' && !currentBand) event.preventDefault();
  });
});

document.querySelectorAll('[data-song-filter]').forEach((button) => {
  button.addEventListener('click', () => {
    currentSongFilter = button.dataset.songFilter;
    document.querySelectorAll('[data-song-filter]').forEach((item) => item.classList.toggle('is-active', item === button));
    renderSongs();
  });
});

document.querySelectorAll('[data-rehearsal-filter]').forEach((button) => {
  button.addEventListener('click', () => {
    currentRehearsalFilter = button.dataset.rehearsalFilter;
    document.querySelectorAll('[data-rehearsal-filter]').forEach((item) => item.classList.toggle('is-active', item === button));
    renderRehearsals();
  });
});

document.getElementById('changeBandButton').addEventListener('click', () => currentBand ? openBandManager() : openQuestionnaire());
document.getElementById('createBandButton').addEventListener('click', () => openBandForm('create'));
document.getElementById('editBandButton').addEventListener('click', () => openBandForm('edit'));
document.getElementById('manageMembersButton').addEventListener('click', openMemberManager);
document.getElementById('addMemberButton').addEventListener('click', () => openMemberForm('create'));
document.getElementById('removeMemberButton').addEventListener('click', removeMember);
document.getElementById('reviewHistoryButton').addEventListener('click', openReviewHistory);
document.getElementById('historySongFilter').addEventListener('change', renderReviewHistory);
document.getElementById('historyProblemFilter').addEventListener('change', renderReviewHistory);

document.getElementById('finishSetupButton').addEventListener('click', openQuestionnaire);

document.getElementById('profileButton').addEventListener('click', openProfileForm);

document.getElementById('notificationsButton').addEventListener('click', () => showInfo(
  'NOTIFICATIONS', 'You are all caught up',
  '<ul class="info-list"><li><strong>Saturday rehearsal confirmed</strong><small>4 of 5 members are available.</small></li><li><strong>Song progress updated</strong><small>Dreams is now almost finished.</small></li></ul>'
));

document.getElementById('addPerformanceButton').addEventListener('click', () => {
  if (!ensureCurrentBand()) return;
  document.getElementById('performanceFormMessage').textContent = '';
  openDialog('performanceModal');
});
document.getElementById('editSetlistButton').addEventListener('click', () => openDialog('setlistModal'));

document.querySelectorAll('[data-checklist-key]').forEach((checkbox) => {
  checkbox.addEventListener('change', () => {
    try { localStorage.setItem(`${userStorageKey('check')}-${checkbox.dataset.checklistKey}`, String(checkbox.checked)); } catch {}
    updateChecklistCount();
    showToast('Equipment checklist updated.');
  });
});

document.querySelectorAll('[data-ai-suggestion]').forEach((button) => {
  button.addEventListener('click', () => renderAiResult(button.dataset.aiSuggestion));
});
document.getElementById('editPlanButton').addEventListener('click', togglePlanEditing);
document.getElementById('approvePlanButton').addEventListener('click', approvePlan);

initializeBandRoleSelects();
renderSongs();
renderRehearsals();

async function initializeBandData() {
  await loadBands();
  await loadCurrentUser();
  if (!currentBand) {
    songs = [];
    rehearsals = [];
    renderSongs();
    renderRehearsals();
    updateQuestionnaireCard();
    showView('songs');
    openQuestionnaire();
    return;
  }
  await loadSongs();
  await loadRehearsals();
  restoreLocalChoices();
  await loadQuestionnaire();
  const firstView = window.location.hash.slice(1);
  showView(pageTitles[firstView] ? firstView : 'overview', false);
}

async function initializeApp() {
  await checkApi();
  if (!apiOnline) {
    showAuthScreen('The BandPilot server is offline. Start it and refresh this page.');
    return;
  }
  try {
    const data = await request('/auth/session', { skipAuthRedirect: true });
    csrfToken = data.csrf_token || '';
    if (!data.authenticated || !data.user) {
      showAuthScreen();
      return;
    }
    await beginAuthenticatedSession(data);
  } catch (error) {
    showAuthScreen(error.message);
  }
}

initializeApp();
