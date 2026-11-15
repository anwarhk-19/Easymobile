import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { api, tokenStore } from './api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [ready, setReady] = useState(false);

    const refresh = useCallback(async () => {
        if (!tokenStore.get()) { setUser(null); setReady(true); return null; }
        try {
            const { user } = await api('/auth/me');
            setUser(user);
            return user;
        } catch {
            setUser(null);
            return null;
        } finally {
            setReady(true);
        }
    }, []);

    useEffect(() => {
        refresh();
        const onUnauth = () => setUser(null);
        window.addEventListener('bmh:unauthorized', onUnauth);
        return () => window.removeEventListener('bmh:unauthorized', onUnauth);
    }, [refresh]);

    const login = (token, u) => { tokenStore.set(token); setUser(u); };
    const logout = async () => {
        try { await api('/auth/logout', { method: 'POST' }); } catch { /* ignore */ }
        tokenStore.set(null);
        setUser(null);
    };

    return <AuthContext.Provider value={{ user, ready, login, logout, refresh, setUser }}>{children}</AuthContext.Provider>;
}

export const useAuth = () => useContext(AuthContext);

export const homeFor = (role) => ({ merchant: '/merchant', technician: '/technician', admin: '/admin', support: '/admin' })[role] || '/';
