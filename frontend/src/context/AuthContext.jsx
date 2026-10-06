import React, { createContext, useState, useEffect, useContext } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';

// Create the AuthContext
export const AuthContext = createContext({
  user: null,
  households: [],
  loading: true,
  login: () => {},
  logout: () => {},
});

// Hook to consume AuthContext
export const useAuth = () => useContext(AuthContext);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [households, setHouseholds] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  // Initial authentication check
  useEffect(() => {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      setUser(null);
      setHouseholds([]);
      setLoading(false);
      return;
    }
    const fetchUser = async () => {
      try {
        const response = await api.get('/me', {
          headers: { Authorization: `Bearer ${token}` },
        });
        setUser(response.data.user);
        setHouseholds(response.data.households ?? []);
      } catch (err) {
        if (err.response && err.response.status === 401) {
          localStorage.removeItem('auth_token');
          localStorage.removeItem('auth_user');
          setUser(null);
          setHouseholds([]);
        }
        // network errors keep token but user null
      } finally {
        setLoading(false);
      }
    };
    fetchUser();
  }, []);

  const login = (user, token) => {
    localStorage.setItem('auth_token', token);
    localStorage.setItem('auth_user', JSON.stringify(user));
    setUser(user);
  };

  const logout = async () => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      try {
        await api.post('/logout', {}, { headers: { Authorization: `Bearer ${token}` } });
      } catch (e) {
        // ignore errors
      }
    }
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
    setUser(null);
    setHouseholds([]);
    navigate('/login');
  };

  return (
    <AuthContext.Provider value={{ user, households, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
};
