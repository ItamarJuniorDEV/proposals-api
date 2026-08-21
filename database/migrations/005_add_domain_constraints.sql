ALTER TABLE proposals
    ADD CONSTRAINT proposals_status_valid
        CHECK (status IN ('draft', 'sent', 'approved', 'rejected', 'expired')),
    ADD CONSTRAINT proposals_discount_percent_valid
        CHECK (discount_percent >= 0 AND discount_percent <= 100);

ALTER TABLE proposal_items
    ADD CONSTRAINT proposal_items_quantity_positive
        CHECK (quantity > 0),
    ADD CONSTRAINT proposal_items_unit_price_positive
        CHECK (unit_price > 0);

ALTER TABLE contracts
    ADD CONSTRAINT contracts_total_amount_non_negative
        CHECK (total_amount >= 0);
