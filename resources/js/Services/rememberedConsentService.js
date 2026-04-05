import axios from 'axios';

export const revokeRememberedConsent = async (consentId, payload) => {
    return axios.post(route('admin.remembered-consents.revoke', consentId), payload);
};
