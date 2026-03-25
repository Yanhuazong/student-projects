import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { buildClassPath, getActiveClassSlug } from '../utils/classRouting';

export default function ProtectedRoute({ children }) {
  const { authLoading, user } = useAuth();
  const location = useLocation();
  const activeClassSlug = getActiveClassSlug(location.pathname);

  if (authLoading) {
    return <div className="page-state">Checking your session...</div>;
  }

  if (!user) {
    return <Navigate to={buildClassPath('/login', activeClassSlug)} replace state={{ from: location.pathname }} />;
  }

  return children;
}
