import { useEffect, useState } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { apiRequest, getDeviceToken } from '../utils/api';
import { buildClassPath, getActiveClassSlug } from '../utils/classRouting';


export default function HomePage() {
  const location = useLocation();
  const activeClassSlug = getActiveClassSlug(location.pathname);
  const [searchParams, setSearchParams] = useSearchParams();
  const [semesters, setSemesters] = useState([]);
  const [currentSemesterId, setCurrentSemesterId] = useState(null);
  const [selectedSemesterId, setSelectedSemesterId] = useState(null);
  const [projects, setProjects] = useState([]);
  const [likedProjectIds, setLikedProjectIds] = useState([]);
  const [likeLimit, setLikeLimit] = useState(3);
  const [homeHeading, setHomeHeading] = useState('Top-rated project stories across every semester.');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    async function loadSemesters() {
      setLoading(true);

      try {
        const [semesterResponse, currentResponse, settingsResponse] = await Promise.all([
          apiRequest('/semesters'),
          apiRequest('/semesters/current'),
          apiRequest('/settings/public'),
        ]);

        if (cancelled) {
          return;
        }

        const allSemesters = semesterResponse.semesters || [];
        const currentSemester = currentResponse.semester;
        const querySemester = Number(searchParams.get('semester')) || 0;
        const defaultSemesterId = querySemester || null;

        setSemesters(allSemesters);
        setCurrentSemesterId(currentSemester?.id || null);
        setSelectedSemesterId(defaultSemesterId);
        setHomeHeading(settingsResponse.settings?.home_heading || 'Top-rated project stories across every semester.');
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

    loadSemesters();

    return () => {
      cancelled = true;
    };
  }, [searchParams]);

  useEffect(() => {
    let cancelled = false;

    async function loadProjects() {
      setLoading(true);
      setError('');

      try {
        const path = selectedSemesterId
          ? `/projects?semester_id=${selectedSemesterId}&device_token=${encodeURIComponent(getDeviceToken())}`
          : '/projects?all_semesters=1&limit=9';
        const response = await apiRequest(path);

        if (cancelled) {
          return;
        }

        setProjects(response.projects || []);
        setLikedProjectIds(response.likes?.project_ids || []);
        setLikeLimit(response.likes?.limit || 3);
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

    loadProjects();

    return () => {
      cancelled = true;
    };
  }, [selectedSemesterId]);

  async function handleLikeToggle(projectId) {
    try {
      const response = await apiRequest(`/projects/${projectId}/like`, {
        method: 'POST',
        body: { device_token: getDeviceToken() },
      });

      const isLiked = Boolean(response.liked);
      setLikeLimit(response.limit || 3);
      setLikedProjectIds((currentLikedProjectIds) => {
        if (isLiked) {
          return currentLikedProjectIds.includes(projectId)
            ? currentLikedProjectIds
            : [...currentLikedProjectIds, projectId];
        }

        return currentLikedProjectIds.filter((id) => id !== projectId);
      });
      setProjects((currentProjects) =>
        currentProjects.map((project) =>
          project.id === projectId
            ? { ...project, like_count: response.like_count }
            : project,
        ),
      );
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  function chooseSemester(semesterId) {
    setSelectedSemesterId(semesterId);
    setSearchParams(semesterId ? { semester: String(semesterId) } : {});
  }

  const selectedSemester = semesters.find((semester) => semester.id === selectedSemesterId);
  const isTopRatedPage = selectedSemesterId === null;
  const canLike = selectedSemesterId !== null && selectedSemesterId === currentSemesterId;

  function getLikeTooltip() {
    if (isTopRatedPage) {
      return 'Like projects on the semester page.';
    }

    if (!canLike) {
      return 'Likes are available only in the current semester.';
    }

    if (likedProjectIds.length >= likeLimit) {
      return `You already liked ${likeLimit} projects. Unlike one to like another.`;
    }

    return 'Click to like this project.';
  }

  return (
    <main className="page-shell page-shell--home">
      <section className="hero-panel">
        <div>
          <h1>{homeHeading}</h1>

        </div>
      </section>

      {semesters.length > 0 ? (
        <nav className="semester-nav" aria-label="Semester switcher">
          <button
            type="button"
            className={selectedSemesterId === null ? 'semester-pill is-active' : 'semester-pill'}
            onClick={() => chooseSemester(null)}
          >
            Top-rated
          </button>
          {semesters.map((semester) => (
            <button
              key={semester.id}
              type="button"
              className={semester.id === selectedSemesterId ? 'semester-pill is-active' : 'semester-pill'}
              onClick={() => chooseSemester(semester.id)}
            >
              {semester.name}
            </button>
          ))}
        </nav>
      ) : null}

      {selectedSemesterId === null ? (
        <section className="section-heading">
          <div>
            <p className="eyebrow">Homepage feed</p>
            <h2>Top-rated projects</h2>
          </div>
        </section>
      ) : null}

      {selectedSemester ? (
        <section className="section-heading">
          <div>
            <p className="eyebrow">Selected semester</p>
            <h2>{selectedSemester.name}</h2>
          </div>
          {currentSemesterId === selectedSemester.id ? <span className="status-chip">Current semester</span> : null}
        </section>
      ) : null}

      {loading ? <div className="page-state">Loading project showcase...</div> : null}
      {error ? <div className="error-banner">{error}</div> : null}

      {!loading && projects.length === 0 ? (
        <div className="empty-panel">
          {selectedSemesterId === null
            ? 'No published projects are available yet.'
            : 'No published projects are available for this semester yet.'}
        </div>
      ) : null}

      <section className="project-list">
        {projects.map((project, index) => {
          const reversed = index % 2 === 1;
          const isLiked = likedProjectIds.includes(project.id);
          const disableLike = !canLike || (!isLiked && likedProjectIds.length >= likeLimit);
          const likeTooltip = getLikeTooltip();

          return (
            <article
              key={project.id}
              className={reversed ? 'project-row project-row--reverse' : 'project-row'}
            >
              <div className="project-media">
                <img
                  src={project.image_url || 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1000&q=80'}
                  alt={project.title}
                />
              </div>
              <div className="project-content">
                <p className="eyebrow">{project.student_name}</p>
                <h3>{project.title}</h3>
                <p className="project-summary">{project.summary}</p>
                <div className="project-meta">
                  <span>Managed by {project.manager_name}</span>
                </div>
                <div className="project-actions">
                  <Link to={buildClassPath(`/projects/${project.slug}`, activeClassSlug)} className="primary-button">
                    Read more
                  </Link>
                  <div className="like-control" title={likeTooltip} aria-label={likeTooltip}>
                    <span className="like-count">{project.like_count}</span>
                    <button
                      type="button"
                      className={isLiked ? 'like-button is-active' : 'like-button'}
                      onClick={() => handleLikeToggle(project.id)}
                      disabled={disableLike}
                      aria-label={isLiked ? 'Remove like' : 'Like this project'}
                    >
                      {isLiked ? '\u2665' : '\u2661'}
                    </button>
                  </div>
                </div>
              </div>
            </article>
          );
        })}
      </section>
    </main>
  );
}
