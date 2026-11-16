import { Route, Routes } from 'react-router-dom';
import NotFound from '../public/NotFound';
import { AdminDashboard, Listings, Users, UserDetail, Verification, VerificationDetail } from './Core';
import { AdminOrderDetail, AdminOrders, AdminRepairDetail, AdminRepairs, AdminTicketDetail, AdminTickets } from './Operations';
import { Commissions, Refunds, Reviews, Settlements } from './Finance';
import { ActivityLogs, Catalogue, Reports, SearchInsights, Settings, Templates } from './System';
import SystemLogs from './SystemLogs';

export default function AdminRoutes() {
    return (
        <Routes>
            <Route index element={<AdminDashboard />} />
            <Route path="shops" element={<Verification kind="shops" />} />
            <Route path="shops/:id" element={<VerificationDetail kind="shops" />} />
            <Route path="technicians" element={<Verification kind="technicians" />} />
            <Route path="technicians/:id" element={<VerificationDetail kind="technicians" />} />
            <Route path="users" element={<Users />} />
            <Route path="users/:id" element={<UserDetail />} />
            <Route path="products" element={<Listings />} />
            <Route path="orders" element={<AdminOrders />} />
            <Route path="orders/:id" element={<AdminOrderDetail />} />
            <Route path="repairs" element={<AdminRepairs />} />
            <Route path="repairs/:id" element={<AdminRepairDetail />} />
            <Route path="tickets" element={<AdminTickets />} />
            <Route path="tickets/:id" element={<AdminTicketDetail />} />
            <Route path="reviews" element={<Reviews />} />
            <Route path="refunds" element={<Refunds />} />
            <Route path="settlements" element={<Settlements />} />
            <Route path="commissions" element={<Commissions />} />
            <Route path="catalogue" element={<Catalogue />} />
            <Route path="templates" element={<Templates />} />
            <Route path="reports" element={<Reports />} />
            <Route path="searches" element={<SearchInsights />} />
            <Route path="logs" element={<ActivityLogs />} />
            <Route path="settings" element={<Settings />} />
            <Route path="system-logs" element={<SystemLogs />} />
            <Route path="*" element={<NotFound />} />
        </Routes>
    );
}
