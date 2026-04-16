import { useEffect, useState } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { apiRequest, resolveImageUrl } from '../utils/api';
import { buildClassPath, getActiveClassSlug } from '../utils/classRouting';

function categoryIcon(iconKey) {
  const iconMap = {
    trophy: '🏆',
    palette: '🎨',
    gear: '⚙️',
    spark: '✨',
  };

  return iconMap[iconKey] || '★';
}

function calculateTotalVotes(voteCounts) {
  return Object.values(voteCounts || {}).reduce((sum, entry) => sum + Number(entry || 0), 0);
}

function uniqueProjectsById(projects) {
  const seen = new Set();
  return (projects || []).filter((project) => {
    const id = Number(project?.id || 0);

    if (!id || seen.has(id)) {
      return false;
    }

    seen.add(id);
    return true;
  });
}

function dedupeTopRatedSections(sections) {
  return (sections || []).map((section) => ({
    ...section,
    projects: uniqueProjectsById(section.projects || []),
  }));
}

export default function HomePage() {
  const { token, user } = useAuth();
  const location = useLocation();
  const activeClassSlug = getActiveClassSlug(location.pathname);
  const [searchParams, setSearchParams] = useSearchParams();
  const [semesters, setSemesters] = useState([]);
  const [currentSemesterId, setCurrentSemesterId] = useState(null);
  const [selectedSemesterId, setSelectedSemesterId] = useState(null);
  const [projects, setProjects] = useState([]);
  const [voteCategories, setVoteCategories] = useState([]);
  const [userVotes, setUserVotes] = useState({});
  const [topRatedSections, setTopRatedSections] = useState([]);
  const [topRatedSemesterName, setTopRatedSemesterName] = useState('');
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
        setVoteCategories(settingsResponse.vote_categories || []);
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
          ? `/projects?semester_id=${selectedSemesterId}`
          : '/projects?all_semesters=1';
        const response = await apiRequest(path, { token });

        if (cancelled) {
          return;
        }

        setProjects(uniqueProjectsById(response.projects || []));
        setVoteCategories(response.vote_categories || []);
        setUserVotes(response.votes?.user_votes || {});
        setTopRatedSections(dedupeTopRatedSections(response.top_rated?.sections || []));
        setTopRatedSemesterName(response.top_rated?.semester?.name || 'Current semester');
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
  }, [selectedSemesterId, token]);

  async function handleVoteToggle(projectId, categoryId) {
    if (!user) {
      setError('Please sign in to vote for projects.');
      return;
    }

    const previousProjectForCategory = userVotes[String(categoryId)] || null;

    try {
      const response = await apiRequest(`/projects/${projectId}/vote`, {
        method: 'POST',
        body: { category_id: categoryId },
        token,
      });

      setUserVotes(response.user_votes || {});
      setProjects((currentProjects) =>
        currentProjects.map((project) => {
          const voteCounts = { ...(project.vote_counts || {}) };

          if (project.id === projectId) {
            voteCounts[String(categoryId)] = Number(response.vote_count || 0);
          }

          if (
            previousProjectForCategory &&
            previousProjectForCategory !== projectId &&
            project.id === previousProjectForCategory &&
            response.voted
          ) {
            const previousCount = Number(voteCounts[String(categoryId)] || 0);
            voteCounts[String(categoryId)] = Math.max(0, previousCount - 1);
          }

          return {
            ...project,
            vote_counts: voteCounts,
            total_vote_count: calculateTotalVotes(voteCounts),
            like_count: calculateTotalVotes(voteCounts),
          };
        }),
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
  const canVote = selectedSemesterId !== null && selectedSemesterId === currentSemesterId;

  function renderProjectCard(project, options = {}) {
    const {
      keyPrefix = 'project',
      reversed = false,
      showVoteControls = false,
      readOnlyVoteControls = false,
    } = options;

    return (
      <article
        key={`${keyPrefix}-${project.id}`}
        className={reversed ? 'project-row project-row--reverse' : 'project-row'}
      >
        <div className="project-media">
          <img
            src={resolveImageUrl(project.image_url) || 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1000&q=80'}
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
            {showVoteControls || readOnlyVoteControls ? (
              <div className="vote-row" aria-label="Vote categories">
                {voteCategories.map((category) => {
                  const isVoted = Number(userVotes[String(category.id)] || 0) === project.id;
                  const voteCount = Number(project.vote_counts?.[String(category.id)] || 0);
                  const disableVote = readOnlyVoteControls || !canVote || !user;
                  const tooltip = readOnlyVoteControls
                    ? `${category.name}: ${voteCount} votes`
                    : !user
                      ? 'Sign in to vote.'
                      : !canVote
                        ? 'Voting is available only in the current semester.'
                        : isVoted
                          ? `Remove ${category.name} vote`
                          : `Vote ${category.name}`;

                  return (
                    <div key={category.id} className="like-control" title={tooltip} aria-label={tooltip}>
                      <span className="like-count">{voteCount}</span>
                      <button
                        type="button"
                        className={isVoted ? 'like-button is-active' : 'like-button'}
                        onClick={() => {
                          if (!readOnlyVoteControls) {
                            handleVoteToggle(project.id, category.id);
                          }
                        }}
                        disabled={disableVote}
                        aria-label={readOnlyVoteControls ? `${category.name}: ${voteCount} votes` : isVoted ? `Remove ${category.name} vote` : `Vote ${category.name}`}
                      >
                        {categoryIcon(category.icon)}
                      </button>
                    </div>
                  );
                })}
              </div>
            ) : null}
          </div>
          {!showVoteControls && !readOnlyVoteControls && typeof project.category_vote_count === 'number' ? (
            <p className="hero-copy" style={{ marginTop: '10px' }}>
              {project.category_vote_count} votes in this category.
            </p>
          ) : null}
          {!user && showVoteControls ? (
            <p className="hero-copy" style={{ marginTop: '10px' }}>
              <Link to={buildClassPath('/login', activeClassSlug)}>Sign in</Link> to vote in all 4 categories.
            </p>
          ) : null}
        </div>
      </article>
    );
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
            <h2>Top-rated projects</h2>
            {topRatedSemesterName ? <p className="top-rated-subtitle">Showing {topRatedSemesterName}</p> : null}
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

      {isTopRatedPage ? (
        <section className="project-list">
          {topRatedSections.length === 0 ? (
            <div className="empty-panel">No votes have been submitted for the current semester yet.</div>
          ) : null}
          {topRatedSections.map((section) => {
            if (!section.projects?.length) {
              return null;
            }

            return (
              <section key={section.id} className="top-rated-section">
                <div className="top-rated-section__header">
                  <p className="eyebrow">{categoryIcon(section.icon)} {section.name}</p>
                </div>
                <div className="project-list">
                  {section.projects.map((project, index) =>
                    renderProjectCard(project, {
                      keyPrefix: `top-rated-${section.id}`,
                      reversed: index % 2 === 1,
                      showVoteControls: false,
                      readOnlyVoteControls: true,
                    }))}
                </div>
              </section>
            );
          })}

        </section>
      ) : null}

      {!isTopRatedPage ? (
        <section className="project-list">
          {projects.map((project, index) =>
            renderProjectCard(project, {
              reversed: index % 2 === 1,
              showVoteControls: true,
            }))}
        </section>
      ) : null}
    </main>
  );
}
