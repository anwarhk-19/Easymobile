import { lazy, Suspense } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import '../css/app.css';
import { AuthProvider } from './lib/auth';
import { ToastProvider } from './lib/toast';
import { Loading } from './components/ui';
import { PortalLayout, PublicLayout, RequireAuth } from './components/layouts';
import Home from './pages/public/Home';
import Search from './pages/public/Search';
import ProductDetail from './pages/public/ProductDetail';
import { ShopDetail, Shops } from './pages/public/Shops';
import { RepairsHome, TechnicianDetail } from './pages/public/Repairs';
import { Login, StaffLogin } from './pages/public/Login';
import NotFound from './pages/public/NotFound';

const Customer = lazy(() => import('./pages/customer'));
const Merchant = lazy(() => import('./pages/merchant'));
const Technician = lazy(() => import('./pages/technician'));
const Admin = lazy(() => import('./pages/admin'));

function App() {
    return (
        <Suspense fallback={<Loading />}>
            <Routes>
                <Route element={<PublicLayout />}>
                    <Route index element={<Home />} />
                    <Route path="search" element={<Search />} />
                    <Route path="products/:slug" element={<ProductDetail />} />
                    <Route path="shops" element={<Shops />} />
                    <Route path="shops/:slug" element={<ShopDetail />} />
                    <Route path="repairs" element={<RepairsHome />} />
                    <Route path="technicians/:slug" element={<TechnicianDetail />} />
                    <Route path="login" element={<Login />} />
                    <Route path="staff" element={<StaffLogin />} />
                    <Route path="*" element={<Customer />} />
                </Route>
                <Route element={<RequireAuth roles={['merchant']} />}>
                    <Route path="merchant/*" element={<PortalLayout portal="merchant" />}>
                        <Route path="*" element={<Merchant />} />
                    </Route>
                </Route>
                <Route element={<RequireAuth roles={['technician']} />}>
                    <Route path="technician/*" element={<PortalLayout portal="technician" />}>
                        <Route path="*" element={<Technician />} />
                    </Route>
                </Route>
                <Route element={<RequireAuth roles={['admin', 'support']} />}>
                    <Route path="admin/*" element={<PortalLayout portal="admin" />}>
                        <Route path="*" element={<Admin />} />
                    </Route>
                </Route>
                <Route path="*" element={<NotFound />} />
            </Routes>
        </Suspense>
    );
}

createRoot(document.getElementById('app')).render(
    <BrowserRouter>
        <AuthProvider>
            <ToastProvider>
                <App />
            </ToastProvider>
        </AuthProvider>
    </BrowserRouter>,
);

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
