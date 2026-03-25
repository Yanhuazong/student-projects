import { useEffect, useState } from 'react';
import DOMPurify from 'dompurify';
import { Link, useLocation, useParams } from 'react-router-dom';
import { apiRequest } from '../utils/api';
import { buildClassPath, getActiveClassSlug } from '../utils/classRouting';

export default function ProjectPage() {
  const { slug } = useParams();
  const location = useLocation();
  const activeClassSlug = getActiveClassSlug(location.pathname);
  const [project, setProject] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    async function loadProject() {
      try {
        const response = await apiRequest(`/projects/${slug}`);
        if (!cancelled) {
          setProject(response.project);
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(requestError.message);
        }
      }
    }

    loadProject();

    return () => {
      cancelled = true;
    };
  }, [slug]);

  if (error) {
    return <main className="page-shell"><div className="error-banner">{error}</div></main>;
  }

  if (!project) {
    return <main className="page-shell"><div className="page-state">Loading project details...</div></main>;
  }

  return (
    <main className="page-shell page-shell--home">
      <Link to={buildClassPath('/', activeClassSlug)} className="back-link">
        Back to showcase
      </Link>
      <article className="detail-card">
        <img
          className="detail-hero"
          src={project.image_url || 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1600&q=80'}
          alt={project.title}
        />
        <div className="detail-card__body">
          <p className="eyebrow">{project.semester_name}</p>
          <h1>{project.title}</h1>
          <p className="detail-subtitle">
            {project.student_name} • Managed by {project.manager_name}
          </p>
          <div
            className="rich-content"
            dangerouslySetInnerHTML={{
              __html: DOMPurify.sanitize(project.description_html || ''),
            }}
          />
          {project.external_url ? (
            <a className="primary-button" href={project.external_url} target="_blank" rel="noreferrer">
              Visit related link
            </a>
          ) : null}
        </div>
      </article>
    </main>
  );
}
