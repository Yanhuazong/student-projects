import { useEffect, useState } from 'react';
import RichTextEditor from '../components/RichTextEditor';
import { useAuth } from '../contexts/AuthContext';
import { apiRequest, resolveImageUrl, uploadApiRequest } from '../utils/api';

const emptySemesterForm = {
  name: '',
  slug: '',
  starts_on: '',
  ends_on: '',
  is_current: true,
};

const emptyUserForm = {
  name: '',
  email: '',
  password: '',
  role: 'manager',
};

const emptySiteSettingsForm = {
  site_logo_text: 'Student Projects',
  home_heading: 'Top-rated project stories across every semester.',
  manager_registration_code: '',
  password_reset_code: '',
  vote_categories: [
    { id: 0, name: 'Best Overall', icon: 'trophy' },
    { id: 0, name: 'Most Creative', icon: 'palette' },
    { id: 0, name: 'Best Technical Execution', icon: 'gear' },
    { id: 0, name: 'Audience Choice', icon: 'spark' },
  ],
};

const voteCategoryIconOptions = [
  { value: 'trophy', label: 'Trophy' },
  { value: 'palette', label: 'Palette' },
  { value: 'gear', label: 'Gear' },
  { value: 'spark', label: 'Spark' },
  { value: 'star', label: 'Star' },
  { value: 'rocket', label: 'Rocket' },
];

const emptyProjectForm = {
  id: null,
  semester_id: '',
  manager_user_id: '',
  title: '',
  slug: '',
  student_name: '',
  summary: '',
  description_html: '<p>Describe the project, goals, process, and outcomes.</p>',
  image_url: '',
  external_url: '',
  sort_order: 0,
  is_published: true,
};

function generateSlug(value) {
  return String(value || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}

function normalizeVoteCategoriesForForm(categories) {
  const normalized = (categories || []).slice(0, 4).map((category, index) => ({
    id: Number(category.id || 0),
    name: category.name || emptySiteSettingsForm.vote_categories[index].name,
    icon: category.icon || emptySiteSettingsForm.vote_categories[index].icon,
  }));

  while (normalized.length < 4) {
    normalized.push({ ...emptySiteSettingsForm.vote_categories[normalized.length] });
  }

  return normalized;
}

export default function DashboardPage() {
  const { token, user } = useAuth();
  const [projects, setProjects] = useState([]);
  const [semesters, setSemesters] = useState([]);
  const [users, setUsers] = useState([]);
  const [projectForm, setProjectForm] = useState(emptyProjectForm);
  const [semesterForm, setSemesterForm] = useState(emptySemesterForm);
  const [userForm, setUserForm] = useState(emptyUserForm);
  const [siteSettingsForm, setSiteSettingsForm] = useState(emptySiteSettingsForm);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [imageUploading, setImageUploading] = useState(false);
  const [slugManuallyEdited, setSlugManuallyEdited] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function loadData() {
      setLoading(true);

      try {
        const requests = [
          apiRequest('/dashboard/projects', { token }),
          apiRequest('/semesters'),
        ];

        if (user.role === 'admin') {
          requests.push(apiRequest('/admin/users', { token }));
          requests.push(apiRequest('/admin/settings', { token }));
        }

        const responses = await Promise.all(requests);

        if (cancelled) {
          return;
        }

        setProjects(responses[0].projects || []);
        setSemesters(responses[1].semesters || []);
        setUsers(user.role === 'admin' ? responses[2].users || [] : [user]);
        if (user.role === 'admin') {
          setSiteSettingsForm({
            site_logo_text: responses[3].settings?.site_logo_text || emptySiteSettingsForm.site_logo_text,
            home_heading: responses[3].settings?.home_heading || emptySiteSettingsForm.home_heading,
            manager_registration_code: responses[3].settings?.manager_registration_code || '',
            password_reset_code: responses[3].settings?.password_reset_code || '',
            vote_categories: normalizeVoteCategoriesForForm(responses[3].vote_categories),
          });
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(requestError.message);
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    loadData();

    return () => {
      cancelled = true;
    };
  }, [token, user]);

  async function refreshProjects() {
    const response = await apiRequest('/dashboard/projects', { token });
    setProjects(response.projects || []);
  }

  async function refreshSemesters() {
    const response = await apiRequest('/semesters');
    setSemesters(response.semesters || []);
  }

  async function refreshUsers() {
    if (user.role !== 'admin') {
      return;
    }

    const response = await apiRequest('/admin/users', { token });
    setUsers(response.users || []);
  }

  function beginEditProject(project) {
    setSlugManuallyEdited(true);
    setProjectForm({
      id: project.id,
      semester_id: String(project.semester_id),
      manager_user_id: String(project.manager_user_id),
      title: project.title,
      slug: project.slug,
      student_name: project.student_name,
      summary: project.summary,
      description_html: project.description_html || '',
      image_url: project.image_url || '',
      external_url: project.external_url || '',
      sort_order: project.sort_order,
      is_published: Boolean(project.is_published),
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function handleProjectSubmit(event) {
    event.preventDefault();
    setError('');
    setMessage('');

    if (imageUploading) {
      setError('Please wait for the image upload to finish before saving the project.');
      return;
    }

    const descriptionText = projectForm.description_html
      .replace(/<[^>]*>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .trim();

    if (!descriptionText) {
      setError('Description is required before saving a project.');
      return;
    }

    try {
      const payload = {
        ...projectForm,
        semester_id: Number(projectForm.semester_id),
        manager_user_id:
          user.role === 'admin' && projectForm.manager_user_id
            ? Number(projectForm.manager_user_id)
            : undefined,
        sort_order: Number(projectForm.sort_order || 0),
      };

      if (projectForm.id) {
        await apiRequest(`/dashboard/projects/${projectForm.id}/update`, {
          method: 'POST',
          body: payload,
          token,
        });
        setMessage('Project updated.');
      } else {
        await apiRequest('/dashboard/projects', {
          method: 'POST',
          body: payload,
          token,
        });
        setMessage('Project created.');
      }

      setProjectForm({
        ...emptyProjectForm,
        manager_user_id: user.role === 'admin' ? '' : String(user.id),
      });
      setSlugManuallyEdited(false);
      await refreshProjects();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleDeleteProject(projectId) {
    setError('');
    setMessage('');

    try {
      await apiRequest(`/dashboard/projects/${projectId}/delete`, {
        method: 'POST',
        token,
      });
      setMessage('Project deleted.');
      await refreshProjects();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleSemesterSubmit(event) {
    event.preventDefault();
    setError('');
    setMessage('');

    try {
      await apiRequest('/admin/semesters', {
        method: 'POST',
        body: semesterForm,
        token,
      });
      setSemesterForm(emptySemesterForm);
      setMessage('Semester created.');
      await refreshSemesters();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleUserSubmit(event) {
    event.preventDefault();
    setError('');
    setMessage('');

    try {
      await apiRequest('/admin/users', {
        method: 'POST',
        body: userForm,
        token,
      });
      setUserForm(emptyUserForm);
      setMessage('User created.');
      await refreshUsers();
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleSiteSettingsSubmit(event) {
    event.preventDefault();
    setError('');
    setMessage('');

    try {
      const response = await apiRequest('/admin/settings', {
        method: 'POST',
        body: siteSettingsForm,
        token,
      });
      setSiteSettingsForm({
        site_logo_text: response.settings?.site_logo_text || emptySiteSettingsForm.site_logo_text,
        home_heading: response.settings?.home_heading || emptySiteSettingsForm.home_heading,
        manager_registration_code: response.settings?.manager_registration_code || '',
        password_reset_code: response.settings?.password_reset_code || '',
        vote_categories: normalizeVoteCategoriesForForm(response.vote_categories),
      });
      setMessage('Site text updated. Refresh the homepage to see changes.');
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function handleImageUpload(event) {
    const file = event.target.files?.[0];

    if (!file) {
      return;
    }

    setError('');
    setMessage('');
    setImageUploading(true);

    try {
      const formData = new FormData();
      formData.append('image', file);

      const response = await uploadApiRequest('/dashboard/uploads/image', {
        method: 'POST',
        body: formData,
        token,
      });

      setProjectForm((current) => ({
        ...current,
        image_url: response.image_url || '',
      }));
      setMessage('Image uploaded and attached to the project form.');
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setImageUploading(false);
    }
  }

  if (loading) {
    return <main className="page-shell"><div className="page-state">Loading dashboard...</div></main>;
  }

  return (
    <main className="page-shell dashboard-shell">
      <section className="section-heading">
        <div>
          <p className="eyebrow">{user.role}</p>
          <h1>Project management dashboard</h1>
        </div>
      </section>

      {message ? <div className="success-banner">{message}</div> : null}
      {error ? <div className="error-banner">{error}</div> : null}

      <section className="dashboard-grid">
        {user.role === 'admin' ? (
          <details className="dashboard-card dashboard-card--accordion">
            <summary>Create semester</summary>
            <div className="dashboard-card__content">
              <form className="stack-form" onSubmit={handleSemesterSubmit}>
                <label>
                  Semester name
                  <input
                    type="text"
                    value={semesterForm.name}
                    onChange={(event) => setSemesterForm({ ...semesterForm, name: event.target.value })}
                    required
                  />
                </label>
                <label>
                  Slug
                  <input
                    type="text"
                    value={semesterForm.slug}
                    onChange={(event) => setSemesterForm({ ...semesterForm, slug: event.target.value })}
                  />
                </label>
                <label>
                  Start date
                  <input
                    type="date"
                    value={semesterForm.starts_on}
                    onChange={(event) => setSemesterForm({ ...semesterForm, starts_on: event.target.value })}
                  />
                </label>
                <label>
                  End date
                  <input
                    type="date"
                    value={semesterForm.ends_on}
                    onChange={(event) => setSemesterForm({ ...semesterForm, ends_on: event.target.value })}
                  />
                </label>
                <label className="checkbox-row">
                  <input
                    type="checkbox"
                    checked={semesterForm.is_current}
                    onChange={(event) => setSemesterForm({ ...semesterForm, is_current: event.target.checked })}
                  />
                  Mark as current semester
                </label>
                <button type="submit" className="secondary-button">Add semester</button>
              </form>
            </div>
          </details>
        ) : null}

        {user.role === 'admin' ? (
          <details className="dashboard-card dashboard-card--accordion">
            <summary>Create user</summary>
            <div className="dashboard-card__content">
              <form className="stack-form" onSubmit={handleUserSubmit}>
                <label>
                  Name
                  <input
                    type="text"
                    value={userForm.name}
                    onChange={(event) => setUserForm({ ...userForm, name: event.target.value })}
                    required
                  />
                </label>
                <label>
                  Email
                  <input
                    type="email"
                    value={userForm.email}
                    onChange={(event) => setUserForm({ ...userForm, email: event.target.value })}
                    required
                  />
                </label>
                <label>
                  Password
                  <input
                    type="password"
                    autoComplete="new-password"
                    value={userForm.password}
                    onChange={(event) => setUserForm({ ...userForm, password: event.target.value })}
                    required
                  />
                </label>
                <label>
                  Role
                  <select
                    value={userForm.role}
                    onChange={(event) => setUserForm({ ...userForm, role: event.target.value })}
                  >
                    <option value="manager">Project manager</option>
                    <option value="user">Regular user</option>
                    <option value="admin">Admin</option>
                  </select>
                </label>
                <button type="submit" className="secondary-button">Add user</button>
              </form>
            </div>
          </details>
        ) : null}

        {user.role === 'admin' ? (
          <details className="dashboard-card dashboard-card--accordion">
            <summary>Homepage text</summary>
            <div className="dashboard-card__content">
              <form className="stack-form" onSubmit={handleSiteSettingsSubmit}>
                <label>
                  Site logo text
                  <input
                    type="text"
                    value={siteSettingsForm.site_logo_text}
                    onChange={(event) => setSiteSettingsForm({ ...siteSettingsForm, site_logo_text: event.target.value })}
                    required
                  />
                </label>
                <label>
                  Home heading
                  <input
                    type="text"
                    value={siteSettingsForm.home_heading}
                    onChange={(event) => setSiteSettingsForm({ ...siteSettingsForm, home_heading: event.target.value })}
                    required
                  />
                </label>
                <label>
                  Manager registration invite code
                  <input
                    type="text"
                    value={siteSettingsForm.manager_registration_code}
                    onChange={(event) => setSiteSettingsForm({ ...siteSettingsForm, manager_registration_code: event.target.value })}
                    placeholder="Leave blank to disable manager self-registration"
                  />
                </label>
                <label>
                  Class password reset code
                  <input
                    type="text"
                    value={siteSettingsForm.password_reset_code}
                    onChange={(event) => setSiteSettingsForm({ ...siteSettingsForm, password_reset_code: event.target.value })}
                    placeholder="Shared temporary code for password recovery"
                  />
                </label>
                {siteSettingsForm.vote_categories.map((category, index) => (
                  <div key={index} className="stack-form__field">
                    <span>{index === 0 ? 'Primary vote category (Best Overall section)' : `Vote category ${index + 1}`}</span>
                    <input
                      type="text"
                      value={category.name}
                      onChange={(event) => {
                        const updated = [...siteSettingsForm.vote_categories];
                        updated[index] = { ...updated[index], name: event.target.value };
                        setSiteSettingsForm({ ...siteSettingsForm, vote_categories: updated });
                      }}
                      required
                    />
                    <select
                      value={category.icon}
                      onChange={(event) => {
                        const updated = [...siteSettingsForm.vote_categories];
                        updated[index] = { ...updated[index], icon: event.target.value };
                        setSiteSettingsForm({ ...siteSettingsForm, vote_categories: updated });
                      }}
                    >
                      {voteCategoryIconOptions.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                      ))}
                    </select>
                  </div>
                ))}
                <button type="submit" className="secondary-button">Save text</button>
              </form>
            </div>
          </details>
        ) : null}
      </section>

      <article className="dashboard-card dashboard-card--wide">
        <h2>{projectForm.id ? 'Edit project' : 'Create project'}</h2>
        <form className="stack-form" onSubmit={handleProjectSubmit}>
          <label>
            Semester
            <select
              value={projectForm.semester_id}
              onChange={(event) => setProjectForm({ ...projectForm, semester_id: event.target.value })}
              required
            >
              <option value="">Select semester</option>
              {semesters.map((semester) => (
                <option key={semester.id} value={semester.id}>
                  {semester.name}
                </option>
              ))}
            </select>
          </label>

          {user.role === 'admin' ? (
            <label>
              Project manager
              <select
                value={projectForm.manager_user_id}
                onChange={(event) => setProjectForm({ ...projectForm, manager_user_id: event.target.value })}
              >
                <option value="">Select manager</option>
                {users
                  .filter((entry) => entry.role === 'manager' || entry.role === 'admin')
                  .map((entry) => (
                    <option key={entry.id} value={entry.id}>
                      {entry.name} ({entry.role})
                    </option>
                  ))}
              </select>
            </label>
          ) : null}

          <label>
            Project title
            <input
              type="text"
              value={projectForm.title}
              onChange={(event) => {
                const nextTitle = event.target.value;
                setProjectForm((current) => ({
                  ...current,
                  title: nextTitle,
                  slug: slugManuallyEdited ? current.slug : generateSlug(nextTitle),
                }));
              }}
              required
            />
          </label>
          <label>
            Slug
            <input
              type="text"
              value={projectForm.slug}
              onChange={(event) => {
                const nextValue = event.target.value;
                const normalized = generateSlug(nextValue);

                if (nextValue.trim() === '') {
                  setSlugManuallyEdited(false);
                  setProjectForm((current) => ({
                    ...current,
                    slug: generateSlug(current.title),
                  }));
                  return;
                }

                setSlugManuallyEdited(true);
                setProjectForm((current) => ({
                  ...current,
                  slug: normalized,
                }));
              }}
            />
          </label>
          <label>
            Student name(s)
            <input
              type="text"
              value={projectForm.student_name}
              onChange={(event) => setProjectForm({ ...projectForm, student_name: event.target.value })}
              required
            />
          </label>
          <label>
            Homepage summary
            <textarea
              rows="4"
              value={projectForm.summary}
              onChange={(event) => setProjectForm({ ...projectForm, summary: event.target.value })}
              required
            />
          </label>
          <div className="stack-form__field">
            <span>Description</span>
            <RichTextEditor
              value={projectForm.description_html}
              token={token}
              onChange={(value) =>
                setProjectForm((current) => ({
                  ...current,
                  description_html: value,
                }))
              }
            />
            <small>Use C/L/R buttons in the editor to insert centered or floated images inside the description.</small>
          </div>
          <label>
            Featured image URL
            <input
              type="text"
              value={projectForm.image_url}
              onChange={(event) => setProjectForm({ ...projectForm, image_url: event.target.value })}
              placeholder="/uploads/projects/your-image.jpg"
            />
          </label>
          <label>
            Upload featured image
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              onChange={handleImageUpload}
              disabled={imageUploading}
            />
            <small>{imageUploading ? 'Uploading image...' : 'Supported formats: JPG, PNG, WEBP, GIF (max 5MB).'}</small>
          </label>
          {projectForm.image_url ? (
            <div>
              <small>Featured image preview</small>
              <img
                src={resolveImageUrl(projectForm.image_url)}
                alt="Featured preview"
                style={{ marginTop: '8px', width: '100%', maxWidth: '480px', height: 'auto', border: '1px solid var(--line)' }}
              />
            </div>
          ) : null}
          <label>
            External link
            <input
              type="url"
              value={projectForm.external_url}
              onChange={(event) => setProjectForm({ ...projectForm, external_url: event.target.value })}
            />
          </label>
          <label className="checkbox-row">
            <input
              type="checkbox"
              checked={projectForm.is_published}
              onChange={(event) => setProjectForm({ ...projectForm, is_published: event.target.checked })}
            />
            Published
          </label>
          <div className="inline-actions">
            <button type="submit" className="primary-button" disabled={imageUploading}>
              {imageUploading
                ? 'Uploading image...'
                : projectForm.id
                  ? 'Save changes'
                  : 'Create project'}
            </button>
            {projectForm.id ? (
              <button
                type="button"
                className="ghost-button"
                onClick={() =>
                  {
                    setSlugManuallyEdited(false);
                    setProjectForm({
                      ...emptyProjectForm,
                      manager_user_id: user.role === 'admin' ? '' : String(user.id),
                    });
                  }
                }
              >
                Cancel edit
              </button>
            ) : null}
          </div>
        </form>
      </article>

      <article className="dashboard-card dashboard-card--wide">
        <h2>{user.role === 'admin' ? 'All projects' : 'Your projects'}</h2>
        <div className="dashboard-list">
          {projects.map((project) => (
            <div key={project.id} className="dashboard-list__item">
              <div>
                <strong>#{project.id} {project.title}</strong>
                <p>
                  {project.student_name} • {project.semester_name} (ID {project.semester_id}) • {project.is_published ? 'Published' : 'Draft'} • {project.like_count} likes
                </p>
              </div>
              <div className="inline-actions">
                <button type="button" className="ghost-button" onClick={() => beginEditProject(project)}>
                  Edit
                </button>
                <button
                  type="button"
                  className="ghost-button ghost-button--danger"
                  onClick={() => handleDeleteProject(project.id)}
                >
                  Delete
                </button>
              </div>
            </div>
          ))}
        </div>
      </article>
    </main>
  );
}
