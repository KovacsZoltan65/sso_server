import axios from 'axios';

export const fetchAuditLog = async (auditLogId) => {
    return axios.get(route('api.admin.audit-logs.show', auditLogId));
};
